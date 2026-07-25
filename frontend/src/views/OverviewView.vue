<script setup lang="ts">
import { computed, onMounted, shallowRef } from 'vue'
import { RouterLink } from 'vue-router'
import { fetchDashboard } from '../api/dashboardApi'
import AccountsHealth from '../components/overview/AccountsHealth.vue'
import BucketProgress from '../components/overview/BucketProgress.vue'
import CashFlowList from '../components/overview/CashFlowList.vue'
import RecentTransactions from '../components/overview/RecentTransactions.vue'
import SafeToSpendHero from '../components/overview/SafeToSpendHero.vue'
import AppIcon, { type IconName } from '../components/ui/AppIcon.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import SkeletonBlock from '../components/ui/SkeletonBlock.vue'
import StatCard from '../components/ui/StatCard.vue'
import type { DashboardData } from '../types/dashboard'
import { BUCKET_LABELS, type Bucket } from '../types/bucket'
import { formatCents } from '../utils/money'

const dashboard = shallowRef<DashboardData | null>(null)
const loading = shallowRef(true)
const error = shallowRef<string | null>(null)
const dismissedAlerts = shallowRef<Set<number>>(new Set())

async function load(): Promise<void> {
  loading.value = true
  error.value = null

  try {
    dashboard.value = await fetchDashboard()
    dismissedAlerts.value = new Set()
  } catch (err) {
    dashboard.value = null
    error.value = err instanceof Error ? err.message : 'Failed to load dashboard.'
  } finally {
    loading.value = false
  }
}

function greetingFor(name: string): string {
  const hour = new Date().getHours()
  const first = name.split(/\s+/)[0] || name
  if (hour < 12) {
    return `Good morning, ${first}`
  }
  if (hour < 18) {
    return `Good afternoon, ${first}`
  }
  return `Good evening, ${first}`
}

const alerts = computed(() => {
  const list = dashboard.value?.safe_to_spend.unusual_alerts ?? []
  return list
    .map((alert, index) => ({ ...alert, index }))
    .filter((alert) => !dismissedAlerts.value.has(alert.index))
})

const glance = computed(() => {
  const data = dashboard.value
  if (!data) {
    return null
  }

  const need = data.safe_to_spend.bucket_actuals.need ?? 0
  const want = data.safe_to_spend.bucket_actuals.want ?? 0
  const savings = data.safe_to_spend.bucket_actuals.savings ?? 0
  const spend = need + want
  const planSpend =
    (data.safe_to_spend.bucket_targets.need ?? 0) + (data.safe_to_spend.bucket_targets.want ?? 0)
  const spendPct = planSpend > 0 ? Math.round((spend * 100) / planSpend) : 0

  const buckets: Bucket[] = ['need', 'want', 'savings']
  const top = buckets
    .map((bucket) => ({ bucket, cents: data.safe_to_spend.bucket_actuals[bucket] ?? 0 }))
    .sort((a, b) => b.cents - a.cents)[0]
  const totalBucket = need + want + savings
  const topPct = totalBucket > 0 ? Math.round((top.cents * 100) / totalBucket) : 0

  const income = data.cash_flows
    .filter((flow) => flow.kind === 'income')
    .reduce((sum, flow) => sum + flow.amount_cents, 0)
  const bills = data.cash_flows
    .filter((flow) => flow.kind === 'bill')
    .reduce((sum, flow) => sum + flow.amount_cents, 0)

  return {
    spend,
    spendPct,
    topLabel: BUCKET_LABELS[top.bucket],
    topCents: top.cents,
    topPct,
    netPlanned: income - bills,
  }
})

function dismissAlert(index: number): void {
  const next = new Set(dismissedAlerts.value)
  next.add(index)
  dismissedAlerts.value = next
}

const quickActions: Array<{
  to: string
  title: string
  body: string
  icon: IconName
  tone: 'accent' | 'need' | 'want'
  description: (count: number) => string
}> = [
  {
    to: '/activity',
    title: 'View Activity',
    body: 'See your recent transactions.',
    icon: 'list',
    tone: 'need',
    description: () => 'See your recent transactions.',
  },
  {
    to: '/review',
    title: 'Review',
    body: 'Categorize waiting transactions.',
    icon: 'review',
    tone: 'accent',
    description: (count) =>
      count > 0 ? `Categorize ${count} waiting.` : 'Nothing waiting to categorize.',
  },
  {
    to: '/rules',
    title: 'Manage Rules',
    body: 'Automate categorization.',
    icon: 'target',
    tone: 'want',
    description: () => 'Automate categorization.',
  },
]

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="overview">
    <p v-if="loading" class="sr-only" role="status">Loading overview…</p>

    <template v-if="loading">
      <SkeletonBlock height="2.5rem" width="18rem" />
      <SkeletonBlock height="12rem" radius="var(--radius)" />
      <div class="skeleton-row">
        <SkeletonBlock height="6rem" radius="var(--radius)" />
        <SkeletonBlock height="6rem" radius="var(--radius)" />
        <SkeletonBlock height="6rem" radius="var(--radius)" />
      </div>
    </template>

    <EmptyState
      v-else-if="error"
      icon="alert"
      title="Couldn’t load overview"
      :body="error"
    >
      <button type="button" class="btn btn-primary" @click="load">Try again</button>
    </EmptyState>

    <template v-else-if="dashboard && glance">
      <PageHeader
        :title="`${greetingFor(dashboard.persona.name)}`"
        subtitle="Here's your financial overview."
      >
        <template #actions>
          <RouterLink class="btn btn-ghost" to="/activity?new=1">Add transaction</RouterLink>
          <RouterLink
            v-if="dashboard.unreviewed_count > 0"
            class="btn btn-primary"
            to="/review"
          >
            Review {{ dashboard.unreviewed_count }} waiting
          </RouterLink>
          <RouterLink v-else class="btn btn-primary" to="/activity">View activity</RouterLink>
        </template>
      </PageHeader>

      <SafeToSpendHero :forecast="dashboard.safe_to_spend" :plan="dashboard.plan" />

      <div v-if="alerts.length" class="alerts">
        <article v-for="alert in alerts" :key="alert.index" class="alert panel">
          <div class="alert-icon">
            <AppIcon name="alert" :size="18" />
          </div>
          <div class="alert-copy">
            <h3>Unusual purchase</h3>
            <p>{{ alert.message }}</p>
            <RouterLink class="btn btn-ghost" to="/review">Review transaction</RouterLink>
          </div>
          <button
            type="button"
            class="btn btn-icon"
            aria-label="Dismiss alert"
            @click="dismissAlert(alert.index)"
          >
            <AppIcon name="close" :size="16" />
          </button>
        </article>
      </div>

      <div class="quick">
        <RouterLink
          v-for="action in quickActions"
          :key="action.to"
          :to="action.to"
          class="quick-card"
          :data-tone="action.tone"
        >
          <span class="quick-icon">
            <AppIcon :name="action.icon" :size="18" />
          </span>
          <div>
            <strong>{{ action.title }}</strong>
            <p>{{ action.description(dashboard.unreviewed_count) }}</p>
          </div>
          <AppIcon name="chevron-right" :size="16" />
        </RouterLink>
      </div>

      <div class="bottom">
        <div class="left-col">
          <section class="panel glance">
            <header class="panel-header">
              <h2>At a glance</h2>
            </header>
            <div class="panel-rows">
              <StatCard
                label="Monthly spend so far"
                :value="formatCents(glance.spend)"
                :context="`${glance.spendPct}% of plan`"
                icon="trending"
                tone="need"
              />
              <StatCard
                label="Top spending category"
                :value="glance.topLabel"
                :context="`${formatCents(glance.topCents)} · ${glance.topPct}% of spend`"
                icon="star"
                tone="want"
              />
              <StatCard
                label="Net planned cash flow"
                :value="formatCents(glance.netPlanned)"
                context="Planned income minus essential bills"
                icon="bank"
                tone="accent"
              />
            </div>
          </section>

          <BucketProgress :forecast="dashboard.safe_to_spend" :plan="dashboard.plan" />
          <CashFlowList :cash-flows="dashboard.cash_flows" />
          <AccountsHealth :accounts="dashboard.accounts" />
        </div>

        <RecentTransactions :transactions="dashboard.recent_transactions" />
      </div>
    </template>
  </div>
</template>

<style scoped>
.overview {
  display: grid;
  gap: var(--space-5);
}

.skeleton-row {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-4);
}

.alerts {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(17rem, 1fr));
  gap: var(--space-4);
}

.alert {
  grid-template-columns: auto 1fr auto;
  align-items: start;
  gap: var(--space-3);
}

.alert-icon {
  display: grid;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-pill);
  background: var(--danger-soft);
  color: #fca5a5;
}

.alert-copy {
  display: grid;
  gap: var(--space-2);
}

.alert-copy h3 {
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 600;
}

.alert-copy p {
  margin: 0;
  color: var(--text-muted);
  font-size: 0.8125rem;
}

.alert-copy .btn {
  justify-self: start;
}

.quick {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-4);
}

.quick-card {
  display: grid;
  grid-template-columns: auto 1fr auto;
  gap: var(--space-3);
  align-items: center;
  padding: var(--space-4);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: var(--bg-elevated);
  transition:
    border-color 160ms ease,
    transform 160ms ease;
}

.quick-card:hover {
  border-color: var(--border-strong);
  transform: translateY(-1px);
}

.quick-icon {
  display: grid;
  place-items: center;
  width: 3rem;
  height: 3rem;
  border-radius: var(--radius-sm);
  background: var(--bg-soft);
  color: var(--text-muted);
}

.quick-card[data-tone='need'] .quick-icon {
  background: var(--need-soft);
  color: #93c5fd;
}

.quick-card[data-tone='accent'] .quick-icon {
  background: var(--accent-soft);
  color: var(--accent-text);
}

.quick-card[data-tone='want'] .quick-icon {
  background: var(--want-soft);
  color: #fcd34d;
}

.quick-card strong {
  display: block;
  font-size: 0.9375rem;
  font-weight: 600;
}

.quick-card p {
  margin: 0.2rem 0 0;
  color: var(--text-muted);
  font-size: 0.8125rem;
}

.bottom {
  display: grid;
  grid-template-columns: 1fr 1.15fr;
  gap: var(--space-5);
  align-items: start;
}

.left-col {
  display: grid;
  gap: var(--space-5);
}

.glance {
  gap: 0;
}

@media (max-width: 1100px) {
  .bottom,
  .quick,
  .skeleton-row {
    grid-template-columns: 1fr;
  }
}
</style>
