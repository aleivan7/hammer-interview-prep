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
    <header>
      <h2>50 / 30 / 20 plan</h2>
      <p v-if="props.plan">
        {{ props.plan.needs_percent }}/{{ props.plan.wants_percent }}/{{ props.plan.savings_percent }}
        of {{ formatCents(props.plan.monthly_income_cents) }} monthly income
      </p>
    </header>

    <ul class="rows">
      <li v-for="row in rows" :key="row.bucket" :class="row.bucket">
        <div class="row-head">
          <span>{{ BUCKET_LABELS[row.bucket] }}</span>
          <span
            >{{ formatCents(row.actual) }} / {{ formatCents(row.target) }}
            <small>({{ row.pct }}%)</small></span
          >
        </div>
        <div class="track" aria-hidden="true">
          <div class="fill" :style="{ width: `${row.pct}%` }" />
        </div>
      </li>
    </ul>
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

header p {
  margin: 0.35rem 0 0;
  color: var(--text-muted);
  font-size: 0.92rem;
}

.rows {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.9rem;
}

.row-head {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 0.35rem;
  font-size: 0.92rem;
}

.row-head small {
  color: var(--text-dim);
}

.track {
  height: 0.55rem;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.06);
  overflow: hidden;
}

.fill {
  height: 100%;
  border-radius: inherit;
  transition: width 360ms ease;
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
