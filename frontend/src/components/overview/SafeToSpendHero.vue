<script setup lang="ts">
import { computed, shallowRef } from 'vue'
import type { FinancialPlanSummary, SafeToSpend } from '../../types/dashboard'
import { formatCents, formatDollars } from '../../utils/money'
import AppIcon from '../ui/AppIcon.vue'
import DonutChart from '../ui/DonutChart.vue'

const props = defineProps<{
  forecast: SafeToSpend
  plan: FinancialPlanSummary | null
}>()

const expanded = shallowRef(false)

const spendCents = computed(
  () =>
    (props.forecast.bucket_actuals.need ?? 0) + (props.forecast.bucket_actuals.want ?? 0),
)

const savedCents = computed(() => props.forecast.bucket_actuals.savings ?? 0)

const incomeCents = computed(() => props.plan?.monthly_income_cents ?? 0)

const savingsShare = computed(() => {
  if (incomeCents.value <= 0) {
    return '—'
  }
  return `${Math.round((savedCents.value * 100) / incomeCents.value)}%`
})

const segments = computed(() => [
  { value: props.forecast.bucket_actuals.need ?? 0, color: 'var(--need)', label: 'Needs' },
  { value: props.forecast.bucket_actuals.want ?? 0, color: 'var(--want)', label: 'Wants' },
  {
    value: props.forecast.bucket_actuals.savings ?? 0,
    color: 'var(--savings)',
    label: 'Savings',
  },
])

const breakdownTitle = computed(
  () =>
    `Available cash ${formatDollars(props.forecast.breakdown.available_cash)}; remaining income ${formatDollars(props.forecast.breakdown.remaining_expected_income)}; essential bills ${formatDollars(props.forecast.breakdown.upcoming_essential_bills)}; remaining savings target ${formatDollars(props.forecast.breakdown.remaining_savings_target)}; safety buffer ${formatDollars(props.forecast.breakdown.safety_buffer)}`,
)
</script>

<template>
  <section class="hero panel">
    <div class="hero-copy">
      <div class="label-row">
        <p class="label">Safe to spend</p>
        <button
          type="button"
          class="info"
          :title="breakdownTitle"
          :aria-label="breakdownTitle"
        >
          <AppIcon name="info" :size="14" />
        </button>
      </div>
      <p class="amount money">{{ formatDollars(forecast.amount) }}</p>
      <button type="button" class="period" @click="expanded = !expanded">
        this month
        <AppIcon name="chevron-down" :size="14" />
      </button>
    </div>

    <dl class="legend">
      <div>
        <dt><span class="dot income" aria-hidden="true" />Income</dt>
        <dd class="money">{{ formatCents(incomeCents) }}</dd>
      </div>
      <div>
        <dt><span class="dot spend" aria-hidden="true" />Spend</dt>
        <dd class="money">{{ formatCents(spendCents) }}</dd>
      </div>
      <div>
        <dt><span class="dot saved" aria-hidden="true" />Saved</dt>
        <dd class="money">{{ formatCents(savedCents) }}</dd>
      </div>
    </dl>

    <DonutChart
      :segments="segments"
      :center-value="savingsShare"
      center-label="of income saved"
      :size="150"
      :thickness="14"
    />

    <dl v-if="expanded" class="breakdown">
      <div>
        <dt>Available cash</dt>
        <dd class="money">{{ formatDollars(forecast.breakdown.available_cash) }}</dd>
      </div>
      <div>
        <dt>Remaining income</dt>
        <dd class="money">+ {{ formatDollars(forecast.breakdown.remaining_expected_income) }}</dd>
      </div>
      <div>
        <dt>Essential bills</dt>
        <dd class="money">− {{ formatDollars(forecast.breakdown.upcoming_essential_bills) }}</dd>
      </div>
      <div>
        <dt>Remaining savings target</dt>
        <dd class="money">− {{ formatDollars(forecast.breakdown.remaining_savings_target) }}</dd>
      </div>
      <div>
        <dt>Safety buffer</dt>
        <dd class="money">− {{ formatDollars(forecast.breakdown.safety_buffer) }}</dd>
      </div>
    </dl>
  </section>
</template>

<style scoped>
.hero {
  grid-template-columns: 1.1fr 0.9fr auto;
  align-items: center;
  gap: var(--space-6);
  padding: var(--space-6);
}

.hero-copy {
  display: grid;
  gap: var(--space-2);
}

.label-row {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.label {
  margin: 0;
  color: var(--text-muted);
  font-size: 0.8125rem;
}

.info {
  display: grid;
  place-items: center;
  width: 1.35rem;
  height: 1.35rem;
  padding: 0;
  border: 0;
  border-radius: var(--radius-pill);
  background: transparent;
  color: var(--text-dim);
  cursor: help;
}

.amount {
  margin: 0;
  color: var(--accent-text);
  font-size: clamp(2.4rem, 4vw, 3rem);
  font-weight: 700;
  letter-spacing: -0.03em;
  line-height: 1.05;
}

.period {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  justify-self: start;
  padding: 0.35rem 0.65rem;
  border-radius: var(--radius-pill);
  border: 1px solid var(--border);
  background: transparent;
  color: var(--text-muted);
  font-size: 0.78rem;
  cursor: pointer;
}

.legend {
  display: grid;
  gap: var(--space-3);
  margin: 0;
}

.legend div {
  display: flex;
  justify-content: space-between;
  gap: var(--space-4);
}

.legend dt {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  color: var(--text-muted);
  font-size: 0.875rem;
}

.legend dd {
  margin: 0;
  font-weight: 600;
}

.dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-pill);
}

.dot.income {
  background: var(--text-muted);
}

.dot.spend {
  background: var(--need);
}

.dot.saved {
  background: var(--savings);
}

.breakdown {
  grid-column: 1 / -1;
  display: grid;
  gap: var(--space-3);
  margin: 0;
  padding-top: var(--space-4);
  border-top: 1px solid var(--border);
}

.breakdown div {
  display: flex;
  justify-content: space-between;
  gap: var(--space-4);
}

.breakdown dt {
  color: var(--text-muted);
}

.breakdown dd {
  margin: 0;
}

@media (max-width: 1100px) {
  .hero {
    grid-template-columns: 1fr;
    justify-items: start;
  }
}
</style>
