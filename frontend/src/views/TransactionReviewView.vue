<script setup lang="ts">
import { computed, onMounted, onUnmounted, shallowRef, watch } from 'vue'
import { runSmartReview } from '../api/smartReviewApi'
import {
  fetchReviewQueue,
  fetchTransactionSuggestion,
  undoTransactionReview,
  updateTransaction,
} from '../api/transactionApi'
import ReviewActions from '../components/review/ReviewActions.vue'
import ReviewCard from '../components/review/ReviewCard.vue'
import type { Bucket } from '../types/bucket'
import type { SmartReviewResult } from '../types/smartReview'
import type { Transaction, TransactionSuggestion } from '../types/transaction'

const transactions = shallowRef<Transaction[]>([])
const currentIndex = shallowRef(0)
const loading = shallowRef(true)
const updating = shallowRef(false)
const smartRunning = shallowRef(false)
const loadError = shallowRef<string | null>(null)
const updateError = shallowRef<string | null>(null)
const suggestion = shallowRef<TransactionSuggestion | null>(null)
const undoStack = shallowRef<number[]>([])
const smartResult = shallowRef<SmartReviewResult | null>(null)

const currentTransaction = computed(
  () => transactions.value[currentIndex.value] ?? null,
)
const isComplete = computed(
  () => !loading.value && !loadError.value && currentTransaction.value === null,
)
const progressLabel = computed(() => {
  if (!currentTransaction.value) {
    return ''
  }

  return `Transaction ${currentIndex.value + 1} of ${transactions.value.length}`
})

async function loadTransactions(): Promise<void> {
  loading.value = true
  loadError.value = null
  updateError.value = null
  currentIndex.value = 0
  undoStack.value = []
  suggestion.value = null

  try {
    transactions.value = await fetchReviewQueue()
  } catch (error) {
    transactions.value = []
    loadError.value = error instanceof Error ? error.message : 'Failed to load transactions.'
  } finally {
    loading.value = false
  }
}

async function loadSuggestion(transaction: Transaction | null): Promise<void> {
  if (!transaction) {
    suggestion.value = null
    return
  }

  try {
    suggestion.value = await fetchTransactionSuggestion(transaction.id)
  } catch {
    suggestion.value = null
  }
}

async function categorize(bucket: Bucket): Promise<void> {
  const transaction = currentTransaction.value

  if (!transaction || updating.value) {
    return
  }

  updating.value = true
  updateError.value = null

  try {
    await updateTransaction(transaction.id, {
      bucket,
      reviewed: true,
    })

    undoStack.value = [...undoStack.value, transaction.id]
    currentIndex.value += 1
  } catch (error) {
    updateError.value =
      error instanceof Error ? error.message : 'Failed to update this transaction.'
  } finally {
    updating.value = false
  }
}

async function undo(): Promise<void> {
  const lastId = undoStack.value[undoStack.value.length - 1]

  if (lastId == null || updating.value) {
    return
  }

  updating.value = true
  updateError.value = null

  try {
    await undoTransactionReview(lastId)
    undoStack.value = undoStack.value.slice(0, -1)

    if (currentIndex.value > 0) {
      currentIndex.value -= 1
    } else {
      await loadTransactions()
    }
  } catch (error) {
    updateError.value = error instanceof Error ? error.message : 'Failed to undo review.'
  } finally {
    updating.value = false
  }
}

async function handleSmartReview(): Promise<void> {
  if (smartRunning.value || updating.value) {
    return
  }

  smartRunning.value = true
  updateError.value = null

  try {
    smartResult.value = await runSmartReview()
    await loadTransactions()
  } catch (error) {
    updateError.value =
      error instanceof Error ? error.message : 'Smart Review failed.'
  } finally {
    smartRunning.value = false
  }
}

function onKeydown(event: KeyboardEvent): void {
  if (updating.value || loading.value || !currentTransaction.value) {
    if (event.key.toLowerCase() === 'u') {
      event.preventDefault()
      void undo()
    }
    return
  }

  const target = event.target as HTMLElement | null
  if (target && ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)) {
    return
  }

  if (event.key === 'ArrowLeft') {
    event.preventDefault()
    void categorize('want')
  } else if (event.key === 'ArrowRight') {
    event.preventDefault()
    void categorize('need')
  } else if (event.key === 'ArrowDown') {
    event.preventDefault()
    void categorize('savings')
  } else if (event.key.toLowerCase() === 'u') {
    event.preventDefault()
    void undo()
  }
}

watch(currentTransaction, (transaction) => {
  void loadSuggestion(transaction)
})

onMounted(() => {
  void loadTransactions()
  window.addEventListener('keydown', onKeydown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <section class="review-view">
    <header class="intro">
      <div>
        <h2>Transaction review</h2>
        <p>
          Categorize into Needs, Wants, or Savings. Smart Review applies high-confidence rules and
          heuristics — not a hosted LLM.
        </p>
      </div>
      <button
        type="button"
        class="smart"
        :disabled="smartRunning || updating || loading"
        @click="handleSmartReview"
      >
        {{ smartRunning ? 'Running…' : 'Smart Review' }}
      </button>
    </header>

    <div v-if="smartResult" class="smart-summary" role="status">
      Applied {{ smartResult.applied_count }}, skipped {{ smartResult.skipped_count }}
      <span v-if="smartResult.applied[0]">
        · e.g. {{ smartResult.applied[0].merchant }} → {{ smartResult.applied[0].bucket }}
      </span>
    </div>

    <p v-if="loading" class="status" role="status">Loading transactions…</p>

    <div v-else-if="loadError" class="panel error" role="alert">
      <p>{{ loadError }}</p>
      <button type="button" class="retry" @click="loadTransactions">Try again</button>
    </div>

    <div v-else-if="isComplete" class="panel complete" role="status">
      <h2>All caught up</h2>
      <p>There are no unreviewed transactions left to categorize.</p>
      <button type="button" class="retry" @click="loadTransactions">Refresh</button>
    </div>

    <div v-else-if="currentTransaction" class="stack">
      <p class="progress" aria-live="polite">{{ progressLabel }}</p>

      <div class="deck" aria-hidden="true">
        <div class="back-card one" />
        <div class="back-card two" />
      </div>

      <ReviewCard
        :transaction="currentTransaction"
        :suggestion="suggestion"
        :updating="updating"
        @categorize="categorize"
      />

      <ReviewActions
        :updating="updating"
        :can-undo="undoStack.length > 0"
        @categorize="categorize"
        @undo="undo"
      />

      <p v-if="updateError" class="inline-error" role="alert">{{ updateError }}</p>
      <p v-if="updating" class="status" role="status">Saving category…</p>
    </div>
  </section>
</template>

<style scoped>
.review-view {
  display: grid;
  gap: 1.25rem;
  width: min(100%, 36rem);
  margin: 0 auto;
}

.intro {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  align-items: start;
}

.intro h2 {
  margin: 0 0 0.4rem;
  font-family: var(--font-display);
  font-size: 1.6rem;
}

.intro p {
  margin: 0;
  color: var(--text-muted);
}

.smart,
.retry {
  padding: 0.65rem 1rem;
  border-radius: var(--radius-sm);
  border: 0;
  background: var(--savings);
  color: #071018;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
}

.smart:disabled,
.retry:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.smart-summary {
  padding: 0.75rem 1rem;
  border-radius: var(--radius-sm);
  background: var(--savings-soft);
  border: 1px solid rgba(62, 207, 142, 0.35);
}

.panel {
  padding: 1.5rem;
  border-radius: var(--radius);
  border: 1px solid var(--border);
  background: var(--bg-elevated);
}

.panel.error {
  border-color: rgba(240, 113, 120, 0.45);
  background: var(--danger-soft);
}

.panel.complete h2 {
  margin: 0 0 0.5rem;
  font-family: var(--font-display);
}

.panel.complete p {
  margin: 0 0 1rem;
  color: var(--text-muted);
}

.status {
  margin: 0;
  color: var(--text-muted);
}

.progress {
  margin: 0 0 0.75rem;
  color: var(--text-dim);
}

.stack {
  position: relative;
}

.deck {
  position: absolute;
  inset: 2.5rem 0.75rem auto;
  height: 8rem;
  pointer-events: none;
}

.back-card {
  position: absolute;
  inset: 0;
  border-radius: var(--radius-lg);
  border: 1px solid var(--border);
  background: var(--bg-soft);
}

.back-card.one {
  transform: translateY(10px) scale(0.98);
  opacity: 0.7;
}

.back-card.two {
  transform: translateY(18px) scale(0.96);
  opacity: 0.45;
}

.inline-error {
  margin: 0.75rem 0 0;
  color: var(--danger);
}

.retry {
  margin-top: 0.75rem;
  background: var(--need);
}
</style>
