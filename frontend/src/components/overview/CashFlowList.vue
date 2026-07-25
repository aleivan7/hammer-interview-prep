<script setup lang="ts">
import type { PlannedCashFlow } from '../../types/dashboard'
import { formatDollars, formatShortDate } from '../../utils/money'

defineProps<{
  cashFlows: PlannedCashFlow[]
}>()
</script>

<template>
  <section class="panel">
    <header class="panel-header">
      <div>
        <h2>Cash flows</h2>
        <p>Upcoming income and essential bills</p>
      </div>
    </header>

    <ul v-if="cashFlows.length" class="panel-rows">
      <li v-for="flow in cashFlows" :key="flow.id" class="row">
        <div>
          <strong>{{ flow.name }}</strong>
          <span>{{ formatShortDate(flow.due_on) }} · {{ flow.kind }}</span>
        </div>
        <span class="amount money" :data-kind="flow.kind">{{ formatDollars(flow.amount) }}</span>
      </li>
    </ul>
    <p v-else class="empty">No planned cash flows this period.</p>
  </section>
</template>

<style scoped>
.row {
  display: flex;
  justify-content: space-between;
  gap: var(--space-4);
  padding: var(--space-3) 0;
}

.row div {
  display: grid;
  gap: 0.15rem;
}

.row strong {
  font-size: 0.875rem;
  font-weight: 500;
}

.row span {
  color: var(--text-muted);
  font-size: 0.75rem;
}

.amount {
  color: var(--text);
  font-size: 0.875rem;
  font-weight: 600;
}

.amount[data-kind='income'] {
  color: var(--accent-text);
}

.amount[data-kind='bill'] {
  color: #fcd34d;
}

.empty {
  margin: 0;
  color: var(--text-muted);
  font-size: 0.875rem;
}
</style>
