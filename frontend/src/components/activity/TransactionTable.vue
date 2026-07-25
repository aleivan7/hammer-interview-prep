<script setup lang="ts">
import { BUCKET_LABELS } from '../../types/bucket'
import type { Transaction } from '../../types/transaction'
import { formatDollars, formatShortDate } from '../../utils/money'

defineProps<{
  transactions: Transaction[]
}>()

const emit = defineEmits<{
  edit: [transaction: Transaction]
}>()
</script>

<template>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Merchant</th>
          <th>Kind</th>
          <th>Bucket</th>
          <th class="num">Amount</th>
          <th>Status</th>
          <th><span class="sr-only">Actions</span></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="tx in transactions" :key="tx.id">
          <td>{{ formatShortDate(tx.transaction_date) }}</td>
          <td>
            <strong>{{ tx.merchant }}</strong>
            <span v-if="tx.account" class="sub">{{ tx.account.name }}</span>
          </td>
          <td>{{ tx.kind }}</td>
          <td>{{ tx.bucket ? BUCKET_LABELS[tx.bucket] : '—' }}</td>
          <td class="num">{{ formatDollars(tx.amount) }}</td>
          <td>
            <span class="badge" :data-reviewed="tx.reviewed">
              {{ tx.reviewed ? 'Reviewed' : 'Queued' }}
            </span>
          </td>
          <td>
            <button type="button" @click="emit('edit', tx)">Edit</button>
          </td>
        </tr>
      </tbody>
    </table>
    <p v-if="!transactions.length" class="empty">No transactions match these filters.</p>
  </div>
</template>

<style scoped>
.table-wrap {
  overflow-x: auto;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: var(--bg-elevated);
}

table {
  width: 100%;
  border-collapse: collapse;
  min-width: 48rem;
}

th,
td {
  padding: 0.85rem 1rem;
  text-align: left;
  border-bottom: 1px solid var(--border);
  vertical-align: top;
}

th {
  color: var(--text-dim);
  font-size: 0.78rem;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  font-weight: 600;
}

td strong {
  display: block;
}

.sub {
  display: block;
  margin-top: 0.15rem;
  color: var(--text-muted);
  font-size: 0.82rem;
}

.num {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.badge {
  display: inline-block;
  padding: 0.2rem 0.5rem;
  border-radius: 999px;
  font-size: 0.78rem;
  background: var(--want-soft);
  color: #ffe7a1;
}

.badge[data-reviewed='true'] {
  background: var(--savings-soft);
  color: #b7f5d4;
}

button {
  padding: 0.35rem 0.7rem;
  border-radius: var(--radius-sm);
  border: 1px solid var(--border-strong);
  background: transparent;
  color: var(--text);
  cursor: pointer;
}

.empty {
  margin: 0;
  padding: 1.25rem;
  color: var(--text-muted);
}
</style>
