<script setup lang="ts">
import { computed } from 'vue'
import type { FinancialPlanSummary, SafeToSpend } from '../../types/dashboard'
import { BUCKET_LABELS, type Bucket } from '../../types/bucket'
import { formatCents } from '../../utils/money'

const props = defineProps<{
  forecast: SafeToSpend
  plan: FinancialPlanSummary | null
}>()

const rows = computed(() => {
  const buckets: Bucket[] = ['need', 'want', 'savings']

  return buckets.map((bucket) => {
    const actual = props.forecast.bucket_actuals[bucket] ?? 0
    const target = props.forecast.bucket_targets[bucket] ?? 0
    const pct = target > 0 ? Math.min(100, Math.round((actual * 100) / target)) : 0

    return { bucket, actual, target, pct }
  })
})
</script>

<template>
  <section class="panel">
    <header class="panel-header">
      <div>
        <h2>50 / 30 / 20 plan</h2>
        <p v-if="plan">
          {{ plan.needs_percent }}/{{ plan.wants_percent }}/{{ plan.savings_percent }} of
          {{ formatCents(plan.monthly_income_cents) }} monthly income
        </p>
      </div>
    </header>

    <ul class="rows">
      <li v-for="row in rows" :key="row.bucket" :class="row.bucket">
        <div class="row-head">
          <span>{{ BUCKET_LABELS[row.bucket] }}</span>
          <span class="money"
            >{{ formatCents(row.actual) }} / {{ formatCents(row.target) }}
            <small>({{ row.pct }}%)</small></span
          >
        </div>
        <div class="meter" aria-hidden="true">
          <div class="meter-fill fill" :style="{ width: `${row.pct}%` }" />
        </div>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.rows {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: var(--space-4);
}

.row-head {
  display: flex;
  justify-content: space-between;
  gap: var(--space-4);
  margin-bottom: var(--space-2);
  font-size: 0.875rem;
}

.row-head small {
  color: var(--text-dim);
}

.need .fill {
  background: var(--need);
}

.want .fill {
  background: var(--want);
}

.savings .fill {
  background: var(--savings);
}
</style>
