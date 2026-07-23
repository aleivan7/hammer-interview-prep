<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { fetchTransactions, updateTransaction } from '../api/transactionApi'
import type { Transaction, TransactionCategory } from '../types/transaction'
import TransactionCard from '../components/TransactionCard.vue'

const transactions = ref<Transaction[]>([])
const currentIndex = ref(0)
const loading = ref(true)
const updating = ref(false)
const loadError = ref<string | null>(null)
const updateError = ref<string | null>(null)

const currentTransaction = computed(() => transactions.value[currentIndex.value] ?? null)
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

  try {
    transactions.value = await fetchTransactions()
  } catch (error) {
    transactions.value = []
    loadError.value = error instanceof Error ? error.message : 'Failed to load transactions.'
  } finally {
    loading.value = false
  }
}

async function handleCategorize(category: TransactionCategory): Promise<void> {
  const transaction = currentTransaction.value

  if (!transaction || updating.value) {
    return
  }

  updating.value = true
  updateError.value = null

  try {
    await updateTransaction(transaction.id, {
      category,
      reviewed: true,
    })

    // Advance to the next item in the already-loaded list.
    currentIndex.value += 1
  } catch (error) {
    updateError.value =
      error instanceof Error ? error.message : 'Failed to update this transaction.'
  } finally {
    updating.value = false
  }
}

onMounted(() => {
  void loadTransactions()
})
</script>

<template>
  <section class="review-view">
    <header class="intro">
      <h1>Transaction Review</h1>
      <p>Categorize each unreviewed transaction as a Need, Want, or Debt / Savings.</p>
    </header>

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

    <div v-else-if="currentTransaction" class="review">
      <p class="progress" aria-live="polite">{{ progressLabel }}</p>

      <TransactionCard
        :transaction="currentTransaction"
        :updating="updating"
        @categorize="handleCategorize"
      />

      <p v-if="updateError" class="inline-error" role="alert">{{ updateError }}</p>
      <p v-if="updating" class="status" role="status">Saving category…</p>
    </div>
  </section>
</template>

<style scoped>
.review-view {
  display: grid;
  gap: 1.5rem;
  width: min(100%, 36rem);
  margin: 0 auto;
}

.intro h1 {
  margin: 0 0 0.5rem;
  font-size: 2rem;
  color: #0f172a;
}

.intro p {
  margin: 0;
  color: #475569;
}

.panel {
  padding: 1.5rem;
  border-radius: 0.5rem;
  border: 1px solid #cbd5e1;
  background: #ffffff;
}

.panel.error {
  border-color: #fca5a5;
  background: #fef2f2;
  color: #991b1b;
}

.panel.complete h2 {
  margin: 0 0 0.5rem;
}

.panel.complete p {
  margin: 0 0 1rem;
  color: #334155;
}

.status {
  margin: 0;
  color: #475569;
}

.progress {
  margin: 0 0 0.75rem;
  color: #64748b;
  font-size: 0.95rem;
}

.inline-error {
  margin: 0.75rem 0 0;
  color: #b91c1c;
}

.retry {
  margin-top: 0.75rem;
  padding: 0.6rem 1rem;
  border: 1px solid #334155;
  border-radius: 0.375rem;
  background: #0f172a;
  color: #ffffff;
  font: inherit;
  cursor: pointer;
}

.retry:focus-visible {
  outline: 2px solid #2563eb;
  outline-offset: 2px;
}
</style>
