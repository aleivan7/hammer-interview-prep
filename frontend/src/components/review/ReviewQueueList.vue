<script setup lang="ts">
import { computed, ref } from 'vue'
import type { Bucket } from '../../types/bucket'
import type { Transaction } from '../../types/transaction'
import { formatSigned, signedAmountCents } from '../../utils/transactions'
import { formatShortDate } from '../../utils/money'
import AppIcon from '../ui/AppIcon.vue'
import MerchantAvatar from '../ui/MerchantAvatar.vue'

const props = defineProps<{
  transactions: Transaction[]
  monthLabel: string
  selectedIds: number[]
  updating: boolean
}>()

const emit = defineEmits<{
  'update:selectedIds': [ids: number[]]
  focus: [id: number]
  categorizeSelected: [bucket: Bucket]
  clear: []
  categorizeOne: [id: number, bucket: Bucket]
}>()

const search = ref('')

const filtered = computed(() => {
  const query = search.value.trim().toLowerCase()
  if (!query) {
    return props.transactions
  }
  return props.transactions.filter((tx) => tx.merchant.toLowerCase().includes(query))
})

const selectedSet = computed(() => new Set(props.selectedIds))

const selectedTotal = computed(() =>
  props.transactions
    .filter((tx) => selectedSet.value.has(tx.id))
    .reduce((sum, tx) => sum + signedAmountCents(tx), 0),
)

const allVisibleSelected = computed(
  () =>
    filtered.value.length > 0 &&
    filtered.value.every((tx) => selectedSet.value.has(tx.id)),
)

function toggleAll(): void {
  if (allVisibleSelected.value) {
    const visible = new Set(filtered.value.map((tx) => tx.id))
    emit(
      'update:selectedIds',
      props.selectedIds.filter((id) => !visible.has(id)),
    )
    return
  }

  const next = new Set(props.selectedIds)
  for (const tx of filtered.value) {
    next.add(tx.id)
  }
  emit('update:selectedIds', [...next])
}

function toggleOne(id: number): void {
  if (selectedSet.value.has(id)) {
    emit(
      'update:selectedIds',
      props.selectedIds.filter((value) => value !== id),
    )
    return
  }
  emit('update:selectedIds', [...props.selectedIds, id])
}

function onRowBucket(id: number, event: Event): void {
  const value = (event.target as HTMLSelectElement).value as Bucket | ''
  if (value) {
    emit('categorizeOne', id, value)
  }
}
</script>

<template>
  <section class="panel queue">
    <header class="panel-header">
      <div>
        <h2>{{ monthLabel }}</h2>
        <p>
          {{ transactions.length }}
          {{ transactions.length === 1 ? 'transaction' : 'transactions' }} awaiting review
        </p>
      </div>
    </header>

    <label class="search">
      <AppIcon name="search" :size="16" />
      <span class="sr-only">Search queue</span>
      <input v-model="search" class="field" type="search" placeholder="Search queue…" />
    </label>

    <label class="select-all">
      <input type="checkbox" :checked="allVisibleSelected" @change="toggleAll" />
      Select all visible
    </label>

    <ul class="panel-rows list">
      <li
        v-for="tx in filtered"
        :key="tx.id"
        class="row"
      >
        <input
          type="checkbox"
          :checked="selectedSet.has(tx.id)"
          :disabled="updating"
          @change="toggleOne(tx.id)"
        />
        <button
          type="button"
          class="focus-transaction"
          :aria-label="`Open ${tx.merchant} in focus mode`"
          @click="emit('focus', tx.id)"
        >
          <MerchantAvatar :name="tx.merchant" :size="36" />
          <div>
            <strong>{{ tx.merchant }}</strong>
            <span>{{ formatShortDate(tx.transaction_date) }}</span>
          </div>
        </button>
        <span class="amount money">{{ formatSigned(signedAmountCents(tx)) }}</span>
        <select
          class="field bucket"
          :value="tx.bucket ?? ''"
          :disabled="updating"
          @change="onRowBucket(tx.id, $event)"
        >
          <option value="">—</option>
          <option value="need">Needs</option>
          <option value="want">Wants</option>
          <option value="savings">Savings</option>
        </select>
      </li>
    </ul>

    <footer class="bulk">
      <p>
        {{ selectedIds.length }} selected · Total:
        <span class="money">{{ formatSigned(selectedTotal) }}</span>
      </p>
      <div class="bulk-actions">
        <button type="button" class="btn btn-ghost" :disabled="!selectedIds.length" @click="emit('clear')">
          Clear
        </button>
        <button
          type="button"
          class="btn need-btn"
          :disabled="updating || !selectedIds.length"
          @click="emit('categorizeSelected', 'need')"
        >
          Needs
        </button>
        <button
          type="button"
          class="btn want-btn"
          :disabled="updating || !selectedIds.length"
          @click="emit('categorizeSelected', 'want')"
        >
          Wants
        </button>
        <button
          type="button"
          class="btn savings-btn"
          :disabled="updating || !selectedIds.length"
          @click="emit('categorizeSelected', 'savings')"
        >
          Savings
        </button>
      </div>
    </footer>
  </section>
</template>

<style scoped>
.queue {
  gap: var(--space-3);
  min-width: 0;
}

.search {
  position: relative;
}

.search :deep(.icon) {
  position: absolute;
  left: 0.8rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-dim);
  pointer-events: none;
}

.search .field {
  padding-left: 2.35rem;
}

.select-all {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  color: var(--text-muted);
  font-size: 0.8125rem;
}

.list {
  max-height: 36rem;
  overflow: auto;
}

.row {
  display: grid;
  grid-template-columns: auto 1fr auto 6.5rem;
  gap: var(--space-3);
  align-items: center;
  padding: var(--space-3) 0;
}

.focus-transaction {
  display: flex;
  gap: var(--space-3);
  align-items: center;
  min-width: 0;
  padding: 0;
  border: 0;
  background: transparent;
  color: inherit;
  text-align: left;
  cursor: pointer;
}

.focus-transaction div {
  display: grid;
  gap: 0.1rem;
  min-width: 0;
}

.focus-transaction strong {
  font-size: 0.8125rem;
  font-weight: 500;
}

.focus-transaction span {
  color: var(--text-muted);
  font-size: 0.72rem;
}

.amount {
  font-size: 0.8125rem;
  font-weight: 600;
}

.bucket {
  min-height: 2rem;
  padding: 0.3rem 0.45rem;
  font-size: 0.75rem;
}

.bulk {
  display: grid;
  gap: var(--space-3);
  padding-top: var(--space-3);
  border-top: 1px solid var(--border);
}

.bulk p {
  margin: 0;
  color: var(--text-muted);
  font-size: 0.8125rem;
}

.bulk-actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
}

.need-btn {
  background: var(--need);
  color: #071018;
}

.want-btn {
  background: var(--want);
  color: #071018;
}

.savings-btn {
  background: var(--savings);
  color: var(--accent-ink);
}

@media (max-width: 640px) {
  .row {
    grid-template-columns: auto minmax(0, 1fr) auto;
  }

  .bucket {
    grid-column: 2 / -1;
  }
}
</style>
