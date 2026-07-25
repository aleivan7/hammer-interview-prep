<script setup lang="ts">
import type { PlannedCashFlow } from '../../types/dashboard'
import { formatDollars, formatShortDate } from '../../utils/money'

defineProps<{
  cashFlows: PlannedCashFlow[]
}>()
</script>

<template>
  <section class="panel">
    <header>
      <h2>Cash flows</h2>
      <p>Upcoming income and essential bills</p>
    </header>

    <ul v-if="cashFlows.length">
      <li v-for="flow in cashFlows" :key="flow.id">
        <div>
          <strong>{{ flow.name }}</strong>
          <span>{{ formatShortDate(flow.due_on) }} · {{ flow.kind }}</span>
        </div>
        <span class="amount" :data-kind="flow.kind">{{ formatDollars(flow.amount) }}</span>
      </li>
    </ul>
    <p v-else class="empty">No planned cash flows this period.</p>
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

header h2 {
  margin: 0;
  font-family: var(--font-display);
  font-size: 1.25rem;
}

header p,
.empty {
  margin: 0.35rem 0 0;
  color: var(--text-muted);
  font-size: 0.92rem;
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

.amount[data-kind='income'] {
  color: var(--savings);
}

.amount[data-kind='bill'] {
  color: var(--want);
}
</style>
