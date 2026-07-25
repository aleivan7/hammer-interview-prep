<script setup lang="ts">
import { shallowRef } from 'vue'
import type { SafeToSpend } from '../../types/dashboard'
import { formatDollars } from '../../utils/money'

const props = defineProps<{
  forecast: SafeToSpend
}>()

const expanded = shallowRef(false)
</script>

<template>
  <section class="hero">
    <div class="hero-copy">
      <p class="label">Safe to spend</p>
      <p class="amount">{{ formatDollars(props.forecast.amount) }}</p>
      <p class="meta">
        Through {{ props.forecast.effective_on }} · period {{ props.forecast.period }}
      </p>
      <button type="button" class="toggle" @click="expanded = !expanded">
        {{ expanded ? 'Hide breakdown' : 'Show breakdown' }}
      </button>
    </div>

    <dl v-if="expanded" class="breakdown">
      <div>
        <dt>Available cash</dt>
        <dd>{{ formatDollars(props.forecast.breakdown.available_cash) }}</dd>
      </div>
      <div>
        <dt>Remaining income</dt>
        <dd>+ {{ formatDollars(props.forecast.breakdown.remaining_expected_income) }}</dd>
      </div>
      <div>
        <dt>Essential bills</dt>
        <dd>− {{ formatDollars(props.forecast.breakdown.upcoming_essential_bills) }}</dd>
      </div>
      <div>
        <dt>Remaining savings target</dt>
        <dd>− {{ formatDollars(props.forecast.breakdown.remaining_savings_target) }}</dd>
      </div>
      <div>
        <dt>Safety buffer</dt>
        <dd>− {{ formatDollars(props.forecast.breakdown.safety_buffer) }}</dd>
      </div>
    </dl>
  </section>
</template>

<style scoped>
.hero {
  display: grid;
  gap: 1.25rem;
  padding: 1.75rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  background:
    linear-gradient(135deg, rgba(79, 140, 255, 0.14), transparent 45%),
    linear-gradient(180deg, var(--bg-elevated), var(--bg-soft));
  box-shadow: var(--shadow);
  animation: rise 420ms ease both;
}

.hero-copy {
  display: grid;
  gap: 0.35rem;
}

.label {
  margin: 0;
  color: var(--text-muted);
  letter-spacing: 0.06em;
  text-transform: uppercase;
  font-size: 0.78rem;
}

.amount {
  margin: 0;
  font-family: var(--font-display);
  font-size: clamp(2.4rem, 5vw, 3.4rem);
  font-weight: 700;
  letter-spacing: -0.03em;
  line-height: 1.05;
}

.meta {
  margin: 0;
  color: var(--text-muted);
}

.toggle {
  justify-self: start;
  margin-top: 0.5rem;
  padding: 0.55rem 0.9rem;
  border-radius: var(--radius-sm);
  border: 1px solid var(--border-strong);
  background: transparent;
  color: var(--text);
  cursor: pointer;
}

.breakdown {
  display: grid;
  gap: 0.65rem;
  margin: 0;
  padding-top: 0.5rem;
  border-top: 1px solid var(--border);
}

.breakdown div {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
}

.breakdown dt {
  color: var(--text-muted);
}

.breakdown dd {
  margin: 0;
  font-variant-numeric: tabular-nums;
}

@keyframes rise {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
