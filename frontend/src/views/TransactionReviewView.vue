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
import ReviewQueueList from '../components/review/ReviewQueueList.vue'
import AppIcon from '../components/ui/AppIcon.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import SkeletonBlock from '../components/ui/SkeletonBlock.vue'
import type { Bucket } from '../types/bucket'
import type { SmartReviewResult } from '../types/smartReview'
import type { Transaction, TransactionSuggestion } from '../types/transaction'

type Mode = 'swipe' | 'multi'

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
const mode = shallowRef<Mode>('swipe')
const tipsOpen = shallowRef(false)
const selectedIds = shallowRef<number[]>([])
const bulkUndoIds = shallowRef<number[]>([])
const bulkStatus = shallowRef<string | null>(null)
const wideLayout = shallowRef(true)

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

const remainingQueue = computed(() =>
  transactions.value.slice(currentIndex.value).filter((tx) => !tx.reviewed),
)

function syncWideLayout(): void {
  if (typeof window.matchMedia !== 'function') {
    wideLayout.value = true
    return
  }
  wideLayout.value = window.matchMedia('(min-width: 1100px)').matches
}

async function loadTransactions(): Promise<void> {
  loading.value = true
  loadError.value = null
  updateError.value = null
  currentIndex.value = 0
  undoStack.value = []
  suggestion.value = null
  selectedIds.value = []
  bulkUndoIds.value = []
  bulkStatus.value = null

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
    selectedIds.value = selectedIds.value.filter((id) => id !== transaction.id)
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

function skip(): void {
  if (!currentTransaction.value) {
    return
  }
  if (currentIndex.value < transactions.value.length - 1) {
    currentIndex.value += 1
  }
}

async function categorizeSelected(bucket: Bucket): Promise<void> {
  if (!selectedIds.value.length || updating.value) {
    return
  }

  updating.value = true
  updateError.value = null
  bulkStatus.value = null

  const ids = [...selectedIds.value]
  const results = await Promise.allSettled(
    ids.map((id) => updateTransaction(id, { bucket, reviewed: true })),
  )

  const fulfilled: number[] = []
  let failed = 0

  results.forEach((result, index) => {
    if (result.status === 'fulfilled') {
      fulfilled.push(ids[index])
    } else {
      failed += 1
    }
  })

  if (fulfilled.length) {
    const done = new Set(fulfilled)
    transactions.value = transactions.value.filter((tx) => !done.has(tx.id))
    if (currentIndex.value >= transactions.value.length) {
      currentIndex.value = Math.max(0, transactions.value.length - 1)
    }
    selectedIds.value = []
    bulkUndoIds.value = fulfilled
    bulkStatus.value = `Categorized ${fulfilled.length}${failed ? `, ${failed} failed` : ''}.`
  } else {
    updateError.value = 'Could not categorize selected transactions.'
  }

  updating.value = false
}

async function undoBulk(): Promise<void> {
  if (!bulkUndoIds.value.length || updating.value) {
    return
  }

  updating.value = true
  await loadTransactions()
  bulkUndoIds.value = []
  bulkStatus.value = null
  updating.value = false
}

async function categorizeOne(id: number, bucket: Bucket): Promise<void> {
  if (updating.value) {
    return
  }

  updating.value = true
  updateError.value = null

  try {
    await updateTransaction(id, { bucket, reviewed: true })
    const index = transactions.value.findIndex((tx) => tx.id === id)
    if (index === currentIndex.value) {
      undoStack.value = [...undoStack.value, id]
      currentIndex.value += 1
    } else {
      transactions.value = transactions.value.filter((tx) => tx.id !== id)
      if (index < currentIndex.value) {
        currentIndex.value -= 1
      }
    }
    selectedIds.value = selectedIds.value.filter((value) => value !== id)
  } catch (error) {
    updateError.value =
      error instanceof Error ? error.message : 'Failed to update this transaction.'
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

function jumpTo(id: number): void {
  const index = transactions.value.findIndex((tx) => tx.id === id)
  if (index >= 0) {
    currentIndex.value = index
    mode.value = 'swipe'
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
  syncWideLayout()
  void loadTransactions()
  window.addEventListener('keydown', onKeydown)
  window.addEventListener('resize', syncWideLayout)
})

onUnmounted(() => {
  window.removeEventListener('keydown', onKeydown)
  window.removeEventListener('resize', syncWideLayout)
})
</script>

<template>
  <section class="review-view">
    <PageHeader
      title="Review transactions"
      subtitle="Swipe to categorize, or select several and categorize at once."
    >
      <template #actions>
        <button type="button" class="btn btn-ghost" @click="tipsOpen = !tipsOpen">
          <AppIcon name="info" :size="16" />
          Tips
        </button>
        <button
          type="button"
          class="btn btn-primary"
          :disabled="smartRunning || updating || loading"
          @click="handleSmartReview"
        >
          {{ smartRunning ? 'Running…' : 'Smart Review' }}
        </button>
      </template>
    </PageHeader>

    <div v-if="tipsOpen" class="tips panel">
      <p>Drag left for Wants, right for Needs, down for Savings.</p>
      <p>Keyboard: ← Want · → Need · ↓ Savings · U Undo.</p>
    </div>

    <div class="mode-toggle" role="tablist" aria-label="Review mode">
      <button
        type="button"
        role="tab"
        class="mode"
        :class="{ active: mode === 'swipe' }"
        :aria-selected="mode === 'swipe'"
        @click="mode = 'swipe'"
      >
        Swipe
      </button>
      <button
        type="button"
        role="tab"
        class="mode"
        :class="{ active: mode === 'multi' }"
        :aria-selected="mode === 'multi'"
        @click="mode = 'multi'"
      >
        Multi-select
      </button>
    </div>

    <div v-if="smartResult" class="smart-summary" role="status">
      Applied {{ smartResult.applied_count }}, skipped {{ smartResult.skipped_count }}
      <span v-if="smartResult.applied[0]">
        · e.g. {{ smartResult.applied[0].merchant }} → {{ smartResult.applied[0].bucket }}
      </span>
    </div>

    <p v-if="loading" class="sr-only" role="status">Loading transactions…</p>

    <div v-if="loading" class="loading-grid">
      <SkeletonBlock height="22rem" radius="var(--radius-lg)" />
      <SkeletonBlock height="22rem" radius="var(--radius)" />
    </div>

    <div v-else-if="loadError" class="panel error" role="alert">
      <p>{{ loadError }}</p>
      <button type="button" class="btn btn-primary" @click="loadTransactions">Try again</button>
    </div>

    <div v-else-if="isComplete" role="status">
      <EmptyState
        icon="check"
        title="All caught up"
        body="There are no unreviewed transactions left to categorize."
      >
        <button type="button" class="btn btn-ghost" @click="loadTransactions">Refresh</button>
      </EmptyState>
    </div>

    <div v-else class="workspace">
      <div v-if="wideLayout || mode === 'swipe'" class="swipe-pane">
        <p class="progress" aria-live="polite">{{ progressLabel }}</p>
        <div class="dots" aria-hidden="true">
          <span
            v-for="(tx, index) in transactions.slice(0, 8)"
            :key="tx.id"
            class="dot"
            :class="{ active: index === currentIndex }"
          />
        </div>

        <div class="stack">
          <div class="deck" aria-hidden="true">
            <div class="back-card one" />
            <div class="back-card two" />
          </div>

          <ReviewCard
            v-if="currentTransaction"
            :transaction="currentTransaction"
            :suggestion="suggestion"
            :updating="updating"
            @categorize="categorize"
          />
        </div>

        <ReviewActions
          :updating="updating"
          :can-undo="undoStack.length > 0"
          @categorize="categorize"
          @undo="undo"
          @skip="skip"
        />

        <p v-if="updateError" class="inline-error" role="alert">{{ updateError }}</p>
        <p v-if="updating" class="status" role="status">Saving category…</p>
      </div>

      <ReviewQueueList
        v-if="wideLayout || mode === 'multi'"
        :transactions="remainingQueue"
        :current-id="currentTransaction?.id ?? null"
        :selected-ids="selectedIds"
        :updating="updating"
        @update:selected-ids="selectedIds = $event"
        @jump="jumpTo"
        @categorize-selected="categorizeSelected"
        @clear="selectedIds = []"
        @categorize-one="categorizeOne"
      />
    </div>

    <div v-if="bulkStatus" class="bulk-status panel">
      <p>{{ bulkStatus }}</p>
      <button
        v-if="bulkUndoIds.length"
        type="button"
        class="btn btn-ghost"
        @click="undoBulk"
      >
        Undo {{ bulkUndoIds.length }}
      </button>
    </div>
  </section>
</template>

<style scoped>
.review-view {
  display: grid;
  gap: var(--space-5);
}

.tips p {
  margin: 0;
  color: var(--text-muted);
  font-size: 0.875rem;
}

.tips p + p {
  margin-top: var(--space-2);
}

.mode-toggle {
  display: inline-flex;
  gap: var(--space-1);
  padding: 0.2rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-pill);
  background: var(--bg-elevated);
  justify-self: start;
}

.mode {
  min-height: 2rem;
  padding: 0.35rem 0.9rem;
  border: 0;
  border-radius: var(--radius-pill);
  background: transparent;
  color: var(--text-muted);
  font-size: 0.8125rem;
  font-weight: 600;
  cursor: pointer;
}

.mode.active {
  color: var(--text);
  background: var(--bg-soft);
  box-shadow: inset 0 -2px 0 var(--accent);
}

.smart-summary {
  padding: 0.75rem 1rem;
  border-radius: var(--radius-sm);
  background: var(--savings-soft);
  border: 1px solid rgba(34, 197, 94, 0.35);
}

.loading-grid {
  display: grid;
  grid-template-columns: minmax(0, 26rem) minmax(0, 1fr);
  gap: var(--space-5);
}

.workspace {
  display: grid;
  grid-template-columns: minmax(0, 26rem) minmax(0, 1fr);
  gap: var(--space-5);
  align-items: start;
}

.swipe-pane {
  display: grid;
  gap: var(--space-4);
}

.progress {
  margin: 0;
  color: var(--text-dim);
  font-size: 0.8125rem;
}

.dots {
  display: flex;
  gap: 0.35rem;
}

.dot {
  width: 0.4rem;
  height: 0.4rem;
  border-radius: var(--radius-pill);
  background: rgba(255, 255, 255, 0.15);
}

.dot.active {
  background: var(--accent);
}

.stack {
  position: relative;
}

.deck {
  position: absolute;
  inset: 0.75rem 0.75rem auto;
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
  margin: 0;
  color: var(--danger);
}

.status {
  margin: 0;
  color: var(--text-muted);
}

.panel.error {
  border-color: rgba(239, 68, 68, 0.45);
  background: var(--danger-soft);
}

.bulk-status {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: var(--space-3);
}

.bulk-status p {
  margin: 0;
  color: var(--text-muted);
}

@media (max-width: 1100px) {
  .workspace,
  .loading-grid {
    grid-template-columns: 1fr;
  }
}
</style>
