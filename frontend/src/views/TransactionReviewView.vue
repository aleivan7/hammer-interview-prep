<script setup lang="ts">
import { computed, onMounted, shallowRef, watch } from 'vue'
import { runSmartReview } from '../api/smartReviewApi'
import {
  fetchReviewQueue,
  fetchTransactionSuggestion,
  undoTransactionReview,
  updateTransaction,
} from '../api/transactionApi'
import ReviewFocusDialog from '../components/review/ReviewFocusDialog.vue'
import ReviewQueueList from '../components/review/ReviewQueueList.vue'
import AppIcon from '../components/ui/AppIcon.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import SkeletonBlock from '../components/ui/SkeletonBlock.vue'
import type { Bucket } from '../types/bucket'
import type { SmartReviewResult } from '../types/smartReview'
import type { Transaction, TransactionSuggestion } from '../types/transaction'

const transactions = shallowRef<Transaction[]>([])
const activeMonthKey = shallowRef<string | null>(null)
const loading = shallowRef(true)
const updating = shallowRef(false)
const smartRunning = shallowRef(false)
const loadError = shallowRef<string | null>(null)
const updateError = shallowRef<string | null>(null)
const suggestion = shallowRef<TransactionSuggestion | null>(null)
const smartResult = shallowRef<SmartReviewResult | null>(null)
const tipsOpen = shallowRef(false)
const selectedIds = shallowRef<number[]>([])
const bulkUndoIds = shallowRef<number[]>([])
const bulkStatus = shallowRef<string | null>(null)
const focusOpen = shallowRef(false)
const focusQueueIds = shallowRef<number[]>([])
const focusUndoStack = shallowRef<number[]>([])
const focusCompleted = shallowRef(0)
const focusTotal = shallowRef(0)

function transactionMonth(transaction: Transaction): string {
  return transaction.transaction_date.slice(0, 7)
}

function compareNewestFirst(a: Transaction, b: Transaction): number {
  return (
    b.transaction_date.localeCompare(a.transaction_date) ||
    b.id - a.id
  )
}

function formatMonth(key: string | null): string {
  if (!key) {
    return ''
  }

  const [year, month] = key.split('-').map(Number)
  return new Intl.DateTimeFormat('en-US', {
    month: 'long',
    year: 'numeric',
    timeZone: 'UTC',
  }).format(new Date(Date.UTC(year, month - 1, 1)))
}

const availableMonthKeys = computed(() =>
  [...new Set(transactions.value.map(transactionMonth))].sort().reverse(),
)

const navigableMonthKeys = computed(() => {
  const keys = new Set(availableMonthKeys.value)
  if (activeMonthKey.value) {
    keys.add(activeMonthKey.value)
  }
  return [...keys].sort().reverse()
})

const activeMonthTransactions = computed(() =>
  transactions.value
    .filter((transaction) => transactionMonth(transaction) === activeMonthKey.value)
    .sort(compareNewestFirst),
)

const activeMonthLabel = computed(() => formatMonth(activeMonthKey.value))
const activeMonthIndex = computed(() =>
  activeMonthKey.value ? navigableMonthKeys.value.indexOf(activeMonthKey.value) : -1,
)
const canGoNewer = computed(() => activeMonthIndex.value > 0)
const canGoOlder = computed(
  () =>
    activeMonthIndex.value >= 0 &&
    activeMonthIndex.value < navigableMonthKeys.value.length - 1,
)
const isComplete = computed(
  () => !loading.value && !loadError.value && transactions.value.length === 0,
)

const currentFocusTransaction = computed(() => {
  const currentId = focusQueueIds.value[0]
  return transactions.value.find((transaction) => transaction.id === currentId) ?? null
})

const focusProgressLabel = computed(() => {
  if (!focusTotal.value) {
    return ''
  }

  if (!currentFocusTransaction.value) {
    return `${focusTotal.value} of ${focusTotal.value} reviewed`
  }

  return `Transaction ${Math.min(focusCompleted.value + 1, focusTotal.value)} of ${focusTotal.value}`
})

function closeFocus(): void {
  focusOpen.value = false
  focusQueueIds.value = []
  focusUndoStack.value = []
  focusCompleted.value = 0
  focusTotal.value = 0
  suggestion.value = null
  updateError.value = null
}

function chooseMonth(key: string): void {
  if (key === activeMonthKey.value) {
    return
  }

  closeFocus()
  activeMonthKey.value = key
  selectedIds.value = []
  bulkStatus.value = null
  bulkUndoIds.value = []
}

function goOlder(): void {
  const key = navigableMonthKeys.value[activeMonthIndex.value + 1]
  if (key) {
    chooseMonth(key)
  }
}

function goNewer(): void {
  const key = navigableMonthKeys.value[activeMonthIndex.value - 1]
  if (key) {
    chooseMonth(key)
  }
}

async function loadTransactions(preferredMonth: string | null = activeMonthKey.value): Promise<void> {
  loading.value = true
  loadError.value = null
  updateError.value = null
  closeFocus()
  selectedIds.value = []
  bulkUndoIds.value = []
  bulkStatus.value = null

  try {
    const loaded = await fetchReviewQueue()
    transactions.value = loaded
    const monthKeys = [...new Set(loaded.map(transactionMonth))].sort().reverse()
    activeMonthKey.value = preferredMonth ?? monthKeys[0] ?? null
  } catch (error) {
    transactions.value = []
    activeMonthKey.value = null
    loadError.value = error instanceof Error ? error.message : 'Failed to load transactions.'
  } finally {
    loading.value = false
  }
}

async function loadSuggestion(transaction: Transaction | null): Promise<void> {
  suggestion.value = null
  if (!transaction || !focusOpen.value) {
    return
  }

  const transactionId = transaction.id
  try {
    const nextSuggestion = await fetchTransactionSuggestion(transactionId)
    if (focusOpen.value && currentFocusTransaction.value?.id === transactionId) {
      suggestion.value = nextSuggestion
    }
  } catch {
    if (currentFocusTransaction.value?.id === transactionId) {
      suggestion.value = null
    }
  }
}

function openFocus(startId?: number): void {
  const monthTransactions = activeMonthTransactions.value
  if (!monthTransactions.length) {
    return
  }

  const requestedIndex = startId == null
    ? 0
    : monthTransactions.findIndex((transaction) => transaction.id === startId)
  const startIndex = requestedIndex >= 0 ? requestedIndex : 0
  const ids = monthTransactions.slice(startIndex).map((transaction) => transaction.id)

  focusQueueIds.value = ids
  focusUndoStack.value = []
  focusCompleted.value = 0
  focusTotal.value = ids.length
  updateError.value = null
  focusOpen.value = true
}

async function categorize(bucket: Bucket): Promise<void> {
  const transaction = currentFocusTransaction.value
  if (!transaction || updating.value) {
    return
  }

  updating.value = true
  updateError.value = null

  try {
    await updateTransaction(transaction.id, { bucket, reviewed: true })
    transactions.value = transactions.value.filter((item) => item.id !== transaction.id)
    focusQueueIds.value = focusQueueIds.value.filter((id) => id !== transaction.id)
    focusUndoStack.value = [...focusUndoStack.value, transaction.id]
    focusCompleted.value += 1
    selectedIds.value = selectedIds.value.filter((id) => id !== transaction.id)
  } catch (error) {
    updateError.value =
      error instanceof Error ? error.message : 'Failed to update this transaction.'
  } finally {
    updating.value = false
  }
}

async function undo(): Promise<void> {
  const lastId = focusUndoStack.value[focusUndoStack.value.length - 1]
  if (lastId == null || updating.value) {
    return
  }

  updating.value = true
  updateError.value = null

  try {
    const restored = await undoTransactionReview(lastId)
    if (!transactions.value.some((transaction) => transaction.id === restored.id)) {
      transactions.value = [...transactions.value, restored]
    }
    focusUndoStack.value = focusUndoStack.value.slice(0, -1)
    focusQueueIds.value = [
      restored.id,
      ...focusQueueIds.value.filter((id) => id !== restored.id),
    ]
    focusCompleted.value = Math.max(0, focusCompleted.value - 1)
  } catch (error) {
    updateError.value = error instanceof Error ? error.message : 'Failed to undo review.'
  } finally {
    updating.value = false
  }
}

function skip(): void {
  if (focusQueueIds.value.length <= 1) {
    return
  }

  const [currentId, ...remainingIds] = focusQueueIds.value
  focusQueueIds.value = [...remainingIds, currentId]
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
  const failed: number[] = []

  results.forEach((result, index) => {
    const id = ids[index]
    if (result.status === 'fulfilled') {
      fulfilled.push(id)
    } else {
      failed.push(id)
    }
  })

  if (fulfilled.length) {
    const done = new Set(fulfilled)
    transactions.value = transactions.value.filter((transaction) => !done.has(transaction.id))
    selectedIds.value = failed
    bulkUndoIds.value = fulfilled
    bulkStatus.value = `Categorized ${fulfilled.length}${failed.length ? `, ${failed.length} failed` : ''}.`
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
  updateError.value = null
  const ids = [...bulkUndoIds.value]
  const restoredTransactions: Transaction[] = []

  for (const id of ids) {
    try {
      restoredTransactions.push(await undoTransactionReview(id))
    } catch {
      // Continue restoring the remaining successful updates.
    }
  }

  const restoredIds = new Set(restoredTransactions.map((transaction) => transaction.id))
  transactions.value = [
    ...transactions.value.filter((transaction) => !restoredIds.has(transaction.id)),
    ...restoredTransactions,
  ]
  bulkUndoIds.value = []
  bulkStatus.value = restoredTransactions.length
    ? `Undid ${restoredTransactions.length}.`
    : 'Could not undo bulk categorization.'
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
    transactions.value = transactions.value.filter((transaction) => transaction.id !== id)
    selectedIds.value = selectedIds.value.filter((selectedId) => selectedId !== id)
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
    await loadTransactions(activeMonthKey.value)
  } catch (error) {
    updateError.value =
      error instanceof Error ? error.message : 'Smart Review failed.'
  } finally {
    smartRunning.value = false
  }
}

watch(currentFocusTransaction, (transaction) => {
  void loadSuggestion(transaction)
})

onMounted(() => {
  void loadTransactions(null)
})
</script>

<template>
  <section class="review-view">
    <PageHeader
      title="Review transactions"
      subtitle="Review one month at a time, categorize in bulk, or enter Focus mode."
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
      <p>Select several transactions to categorize them together.</p>
      <p>In Focus mode: drag left for Wants, right for Needs, or down for Savings.</p>
      <p>Keyboard in Focus mode: ← Want · → Need · ↓ Savings · U Undo · Esc Close.</p>
    </div>

    <div v-if="smartResult" class="smart-summary" role="status">
      Applied {{ smartResult.applied_count }}, skipped {{ smartResult.skipped_count }}
      <span v-if="smartResult.applied[0]">
        · e.g. {{ smartResult.applied[0].merchant }} → {{ smartResult.applied[0].bucket }}
      </span>
    </div>

    <p v-if="loading" class="sr-only" role="status">Loading transactions…</p>

    <SkeletonBlock v-if="loading" height="28rem" radius="var(--radius)" />

    <div v-else-if="loadError" class="panel error" role="alert">
      <p>{{ loadError }}</p>
      <button type="button" class="btn btn-primary" @click="loadTransactions(null)">
        Try again
      </button>
    </div>

    <div v-else-if="isComplete" role="status">
      <EmptyState
        icon="check"
        title="All caught up"
        body="There are no unreviewed transactions left to categorize."
      >
        <button type="button" class="btn btn-ghost" @click="loadTransactions(null)">Refresh</button>
      </EmptyState>
    </div>

    <template v-else>
      <div class="month-toolbar panel">
        <div class="month-navigation" aria-label="Review month navigation">
          <button
            type="button"
            class="btn btn-ghost"
            :disabled="!canGoOlder"
            aria-label="Show previous month"
            @click="goOlder"
          >
            <AppIcon name="arrow-left" :size="16" />
            Previous
          </button>
          <div class="month-heading">
            <span>Reviewing</span>
            <strong>{{ activeMonthLabel }}</strong>
          </div>
          <button
            type="button"
            class="btn btn-ghost"
            :disabled="!canGoNewer"
            aria-label="Show next month"
            @click="goNewer"
          >
            Next
            <AppIcon name="arrow-right" :size="16" />
          </button>
        </div>

        <button
          type="button"
          class="btn btn-primary"
          :disabled="updating || !activeMonthTransactions.length"
          @click="openFocus()"
        >
          <AppIcon name="target" :size="16" />
          Start Focus mode
        </button>
      </div>

      <ReviewQueueList
        v-if="activeMonthTransactions.length"
        :key="activeMonthKey ?? undefined"
        :transactions="activeMonthTransactions"
        :month-label="activeMonthLabel"
        :selected-ids="selectedIds"
        :updating="updating"
        @update:selected-ids="selectedIds = $event"
        @focus="openFocus"
        @categorize-selected="categorizeSelected"
        @clear="selectedIds = []"
        @categorize-one="categorizeOne"
      />

      <div v-else class="panel month-empty" role="status">
        <AppIcon name="check" :size="24" />
        <div>
          <h2>{{ activeMonthLabel }} is complete</h2>
          <p>Choose another month to continue reviewing transactions.</p>
        </div>
      </div>

      <p v-if="updateError && !focusOpen" class="inline-error" role="alert">
        {{ updateError }}
      </p>

      <div v-if="bulkStatus" class="bulk-status panel" role="status">
        <p>{{ bulkStatus }}</p>
        <button
          v-if="bulkUndoIds.length"
          type="button"
          class="btn btn-ghost"
          :disabled="updating"
          @click="undoBulk"
        >
          Undo {{ bulkUndoIds.length }}
        </button>
      </div>
    </template>

    <ReviewFocusDialog
      v-if="focusOpen"
      :transaction="currentFocusTransaction"
      :suggestion="suggestion"
      :month-label="activeMonthLabel"
      :progress-label="focusProgressLabel"
      :updating="updating"
      :can-undo="focusUndoStack.length > 0"
      :error="updateError"
      @close="closeFocus"
      @categorize="categorize"
      @undo="undo"
      @skip="skip"
    />
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

.smart-summary {
  padding: 0.75rem 1rem;
  border: 1px solid rgba(34, 197, 94, 0.35);
  border-radius: var(--radius-sm);
  background: var(--savings-soft);
}

.month-toolbar {
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
}

.month-navigation {
  display: grid;
  grid-template-columns: auto minmax(9rem, 1fr) auto;
  align-items: center;
  gap: var(--space-3);
}

.month-heading {
  display: grid;
  justify-items: center;
  gap: 0.1rem;
  text-align: center;
}

.month-heading span {
  color: var(--text-dim);
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.month-heading strong {
  font-size: 1.05rem;
}

.inline-error {
  margin: 0;
  color: var(--danger);
}

.panel.error {
  border-color: rgba(239, 68, 68, 0.45);
  background: var(--danger-soft);
}

.month-empty {
  grid-template-columns: auto 1fr;
  align-items: center;
}

.month-empty h2,
.month-empty p {
  margin: 0;
}

.month-empty h2 {
  font-size: 1.0625rem;
}

.month-empty p {
  margin-top: var(--space-1);
  color: var(--text-muted);
  font-size: 0.875rem;
}

.bulk-status {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
}

.bulk-status p {
  margin: 0;
  color: var(--text-muted);
}

@media (max-width: 760px) {
  .month-toolbar {
    grid-template-columns: 1fr;
  }

  .month-toolbar > .btn {
    width: 100%;
  }
}

@media (max-width: 560px) {
  .month-navigation {
    grid-template-columns: 1fr 1fr;
  }

  .month-heading {
    grid-column: 1 / -1;
    grid-row: 1;
  }

  .month-navigation .btn {
    grid-row: 2;
  }

  .month-navigation .btn:last-child {
    grid-column: 2;
  }
}
</style>
