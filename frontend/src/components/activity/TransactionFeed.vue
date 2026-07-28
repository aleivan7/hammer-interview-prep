<script setup lang="ts">
import { computed, shallowRef, watch } from 'vue'
import type { Transaction } from '../../types/transaction'
import { groupByDate } from '../../utils/dates'
import {
  displayMerchantName,
  formatSigned,
  hasDistinctRawDescriptor,
  rawMerchantDescriptor,
  signedAmountCents,
  summarizeCashFlow,
} from '../../utils/transactions'
import BucketPill from '../ui/BucketPill.vue'
import EmptyState from '../ui/EmptyState.vue'
import MerchantAvatar from '../ui/MerchantAvatar.vue'

const props = defineProps<{
  transactions: Transaction[]
}>()

const emit = defineEmits<{
  edit: [transaction: Transaction]
  clearFilters: []
}>()

const pageSize = 25
const visibleCount = shallowRef(pageSize)

watch(
  () => props.transactions,
  () => {
    visibleCount.value = pageSize
  },
)

const visible = computed(() => props.transactions.slice(0, visibleCount.value))
const groups = computed(() => groupByDate(visible.value))
const remaining = computed(() => Math.max(0, props.transactions.length - visibleCount.value))

function dayNet(transactions: Transaction[]): number {
  return summarizeCashFlow(transactions).netCents
}

function loadMore(): void {
  visibleCount.value += pageSize
}

function categoryLabel(tx: Transaction): string {
  return tx.detailed_category?.name ?? tx.subcategory ?? ''
}
</script>

<template>
  <div class="feed">
    <EmptyState
      v-if="!transactions.length"
      icon="search"
      title="No transactions match these filters"
      body="Try clearing search or bucket filters."
    >
      <button type="button" class="btn btn-ghost" @click="emit('clearFilters')">
        Clear filters
      </button>
    </EmptyState>

    <template v-else>
      <section v-for="group in groups" :key="group.date" class="group">
        <header class="group-head">
          <h2>{{ group.label }}</h2>
          <span class="money" :class="dayNet(group.transactions) >= 0 ? 'credit' : 'debit'">
            {{ formatSigned(dayNet(group.transactions)) }}
          </span>
        </header>

        <div class="panel list">
          <button
            v-for="tx in group.transactions"
            :key="tx.id"
            type="button"
            class="row"
            @click="emit('edit', tx)"
          >
            <span class="sr-only">Edit {{ displayMerchantName(tx) }}</span>
            <MerchantAvatar :name="displayMerchantName(tx)" :size="40" />
            <div class="copy">
              <strong>{{ displayMerchantName(tx) }}</strong>
              <span v-if="hasDistinctRawDescriptor(tx)" class="raw">
                {{ rawMerchantDescriptor(tx) }}
              </span>
              <span
                >{{ tx.account?.name ?? 'No account' }} · {{ tx.kind
                }}{{ categoryLabel(tx) ? ` · ${categoryLabel(tx)}` : ''
                }}{{ tx.reviewed ? '' : ' · Queued' }}</span
              >
            </div>
            <BucketPill :bucket="tx.bucket" />
            <span class="amount money" :class="signedAmountCents(tx) > 0 ? 'credit' : 'debit'">
              {{ formatSigned(signedAmountCents(tx)) }}
            </span>
          </button>
        </div>
      </section>

      <div class="footer">
        <p class="meta">
          Showing 1–{{ visible.length }} of {{ transactions.length }}
        </p>
        <button
          v-if="remaining > 0"
          type="button"
          class="btn btn-ghost"
          @click="loadMore"
        >
          Load more
          <span aria-hidden="true">↓</span>
        </button>
      </div>
    </template>
  </div>
</template>

<style scoped>
.feed {
  display: grid;
  gap: var(--space-5);
  min-width: 0;
}

.group {
  display: grid;
  gap: var(--space-3);
}

.group-head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: var(--space-3);
  padding: 0 var(--space-1);
}

.group-head h2 {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 600;
}

.list {
  gap: 0;
  padding: 0 var(--space-2);
}

.row {
  display: grid;
  grid-template-columns: auto 1fr auto auto;
  gap: var(--space-3);
  align-items: center;
  width: 100%;
  padding: var(--space-3) var(--space-2);
  border: 0;
  border-bottom: 1px solid var(--border);
  background: transparent;
  color: inherit;
  text-align: left;
  cursor: pointer;
}

.row:last-child {
  border-bottom: 0;
}

.row:hover {
  background: var(--bg-hover);
}

.copy {
  display: grid;
  gap: 0.1rem;
  min-width: 0;
}

.copy strong {
  font-size: 0.875rem;
  font-weight: 500;
}

.copy span {
  color: var(--text-muted);
  font-size: 0.72rem;
}

.copy .raw {
  color: var(--text-dim);
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}

.amount {
  min-width: 5.5rem;
  text-align: right;
  font-size: 0.875rem;
  font-weight: 600;
}

.footer {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: var(--space-3);
}

.meta {
  margin: 0;
  color: var(--text-dim);
  font-size: 0.78rem;
}

@media (max-width: 700px) {
  .row {
    grid-template-columns: auto 1fr;
    grid-template-areas:
      'avatar copy'
      'avatar amount'
      'pill pill';
  }

  .row :deep(.avatar) {
    grid-area: avatar;
  }

  .copy {
    grid-area: copy;
  }

  .amount {
    grid-area: amount;
    text-align: left;
  }

  .row :deep(.pill) {
    grid-area: pill;
    justify-self: start;
  }
}
</style>
