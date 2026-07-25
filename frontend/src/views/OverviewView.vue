<script setup lang="ts">
import { onMounted, shallowRef } from 'vue'
import { fetchDashboard } from '../api/dashboardApi'
import AccountsHealth from '../components/overview/AccountsHealth.vue'
import BucketProgress from '../components/overview/BucketProgress.vue'
import CashFlowList from '../components/overview/CashFlowList.vue'
import RecentTransactions from '../components/overview/RecentTransactions.vue'
import SafeToSpendHero from '../components/overview/SafeToSpendHero.vue'
import type { DashboardData } from '../types/dashboard'

const dashboard = shallowRef<DashboardData | null>(null)
const loading = shallowRef(true)
const error = shallowRef<string | null>(null)

async function load(): Promise<void> {
  loading.value = true
  error.value = null

  try {
    dashboard.value = await fetchDashboard()
  } catch (err) {
    dashboard.value = null
    error.value = err instanceof Error ? err.message : 'Failed to load dashboard.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="overview">
    <p v-if="loading" class="status" role="status">Loading overview…</p>

    <div v-else-if="error" class="panel error" role="alert">
      <p>{{ error }}</p>
      <button type="button" @click="load">Try again</button>
    </div>

    <template v-else-if="dashboard">
      <div class="intro">
        <p>
          Welcome back, {{ dashboard.persona.name }}. Laravel owns the totals — this view only
          presents them.
        </p>
        <RouterLink v-if="dashboard.unreviewed_count > 0" class="cta" to="/review">
          Review {{ dashboard.unreviewed_count }} waiting
        </RouterLink>
      </div>

      <SafeToSpendHero :forecast="dashboard.safe_to_spend" />

      <ul v-if="dashboard.safe_to_spend.unusual_alerts.length" class="alerts">
        <li v-for="(alert, index) in dashboard.safe_to_spend.unusual_alerts" :key="index">
          {{ alert.message }}
        </li>
      </ul>

      <div class="grid">
        <BucketProgress :forecast="dashboard.safe_to_spend" :plan="dashboard.plan" />
        <CashFlowList :cash-flows="dashboard.cash_flows" />
        <AccountsHealth :accounts="dashboard.accounts" />
        <RecentTransactions :transactions="dashboard.recent_transactions" />
      </div>
    </template>
  </div>
</template>

<style scoped>
.overview {
  display: grid;
  gap: 1.25rem;
  max-width: 72rem;
}

.intro {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 1rem;
  align-items: center;
}

.intro p {
  margin: 0;
  color: var(--text-muted);
  max-width: 40rem;
}

.cta {
  padding: 0.65rem 1rem;
  border-radius: var(--radius-sm);
  background: var(--need);
  color: #071018;
  font-weight: 600;
  transition: transform 160ms ease;
}

.cta:hover {
  transform: translateY(-1px);
}

.alerts {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.5rem;
}

.alerts li {
  padding: 0.75rem 1rem;
  border-radius: var(--radius-sm);
  border: 1px solid rgba(240, 193, 75, 0.35);
  background: var(--want-soft);
  color: #ffe7a1;
}

.grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem;
}

.status,
.panel {
  margin: 0;
}

.panel.error {
  padding: 1rem;
  border-radius: var(--radius);
  border: 1px solid rgba(240, 113, 120, 0.4);
  background: var(--danger-soft);
}

.panel.error button {
  margin-top: 0.75rem;
  padding: 0.55rem 0.9rem;
  border-radius: var(--radius-sm);
  border: 1px solid var(--border-strong);
  background: transparent;
  color: var(--text);
  cursor: pointer;
}

@media (max-width: 900px) {
  .grid {
    grid-template-columns: 1fr;
  }
}
</style>
