<script setup lang="ts">
import { BUCKET_LABELS } from '../../types/bucket'
import type { Transaction } from '../../types/transaction'
import { formatDollars, formatShortDate } from '../../utils/money'

defineProps<{
  transactions: Transaction[]
}>()
</script>

<template>
  <section class="panel">
    <header>
      <h2>Recent activity</h2>
      <RouterLink class="link" to="/activity">View all</RouterLink>
    </header>

    <ul v-if="transactions.length">
      <li v-for="tx in transactions" :key="tx.id">
        <div>
          <strong>{{ tx.merchant }}</strong>
          <span
            >{{ formatShortDate(tx.transaction_date) }} ·
            {{ tx.bucket ? BUCKET_LABELS[tx.bucket] : 'Unreviewed' }}</span
          >
        </div>
        <span class="amount">{{ formatDollars(tx.amount) }}</span>
      </li>
    </ul>
    <p v-else class="empty">No recent transactions.</p>
  </section>
</template>

<style scoped>
.panel {
  display: grid;
  gap: 1rem;
  padding: 1.25rem;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: var(--bg-elevated);
}

header {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 1rem;
}

header h2 {
  margin: 0;
  font-family: var(--font-display);
  font-size: 1.25rem;
}

.link {
  color: var(--need);
  font-size: 0.9rem;
}

.empty {
  margin: 0;
  color: var(--text-muted);
}

ul {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.75rem;
}

li {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
}

li div {
  display: grid;
  gap: 0.15rem;
}

li span {
  color: var(--text-muted);
  font-size: 0.85rem;
}

.amount {
  color: var(--text);
  font-variant-numeric: tabular-nums;
}
</style>
