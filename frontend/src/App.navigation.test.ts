/**
 * App shell navigation
 * - mounts Overview and can reach Activity, Review, and Rules routes
 */
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter, type Router } from 'vue-router'
import App from './App.vue'
import { fetchDashboard } from './api/dashboardApi'
import { fetchAccounts } from './api/accountApi'
import { fetchReviewQueue, fetchTransactionSuggestion } from './api/transactionApi'
import { fetchRules } from './api/rulesApi'
import { fetchTransactions } from './api/transactionApi'
import ActivityView from './views/ActivityView.vue'
import OverviewView from './views/OverviewView.vue'
import RulesView from './views/RulesView.vue'
import TransactionReviewView from './views/TransactionReviewView.vue'

vi.mock('./api/dashboardApi', () => ({
  fetchDashboard: vi.fn(),
}))

vi.mock('./api/accountApi', () => ({
  fetchAccounts: vi.fn(),
}))

vi.mock('./api/transactionApi', () => ({
  fetchTransactions: vi.fn(),
  fetchReviewQueue: vi.fn(),
  fetchTransactionSuggestion: vi.fn(),
  updateTransaction: vi.fn(),
  createTransaction: vi.fn(),
  undoTransactionReview: vi.fn(),
}))

vi.mock('./api/rulesApi', () => ({
  fetchRules: vi.fn(),
  createRule: vi.fn(),
  updateRule: vi.fn(),
  deleteRule: vi.fn(),
}))

vi.mock('./api/smartReviewApi', () => ({
  runSmartReview: vi.fn(),
}))

function makeRouter(): Router {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'overview', component: OverviewView, meta: { title: 'Overview' } },
      { path: '/activity', name: 'activity', component: ActivityView, meta: { title: 'Activity' } },
      {
        path: '/review',
        name: 'review',
        component: TransactionReviewView,
        meta: { title: 'Review' },
      },
      { path: '/rules', name: 'rules', component: RulesView, meta: { title: 'Rules' } },
    ],
  })
}

beforeEach(() => {
  vi.mocked(fetchDashboard).mockResolvedValue({
    persona: {
      name: 'Jordan Lee',
      email: 'jordan.lee@clearspend.demo',
      member_since: '2026-01-01',
    },
    safe_to_spend: {
      safe_to_spend_cents: 10000,
      amount: '100.00',
      effective_on: '2026-07-25',
      period: '2026-07',
      breakdown: {
        available_cash_cents: 10000,
        available_cash: '100.00',
        remaining_expected_income_cents: 0,
        remaining_expected_income: '0.00',
        upcoming_essential_bills_cents: 0,
        upcoming_essential_bills: '0.00',
        remaining_savings_target_cents: 0,
        remaining_savings_target: '0.00',
        safety_buffer_cents: 0,
        safety_buffer: '0.00',
      },
      bucket_actuals: { need: 0, want: 0, savings: 0 },
      bucket_targets: { need: 1, want: 1, savings: 1 },
      unusual_alerts: [],
    },
    plan: {
      needs_percent: 50,
      wants_percent: 30,
      savings_percent: 20,
      safety_buffer_cents: 25000,
      safety_buffer: '250.00',
      monthly_income_cents: 520000,
      monthly_income: '5200.00',
    },
    cash_flows: [],
    accounts: [],
    recent_transactions: [],
    unreviewed_count: 2,
  })
  vi.mocked(fetchAccounts).mockResolvedValue([])
  vi.mocked(fetchTransactions).mockResolvedValue([])
  vi.mocked(fetchReviewQueue).mockResolvedValue([])
  vi.mocked(fetchTransactionSuggestion).mockResolvedValue({
    bucket: null,
    subcategory: null,
    confidence: 0,
    source: 'heuristic',
    explanation: 'None',
    auto_review: false,
  })
  vi.mocked(fetchRules).mockResolvedValue([])
})

describe('App navigation shell', () => {
  /** Renders ClearSpend overview, then navigates to Activity, Review, and Rules. */
  it('mounts overview and navigates between primary routes', async () => {
    const router = makeRouter()
    router.push('/')
    await router.isReady()

    const wrapper = mount(App, {
      global: {
        plugins: [router],
      },
    })

    await flushPromises()
    expect(wrapper.text()).toContain('ClearSpend')
    expect(wrapper.text()).toContain('Safe to spend')
    expect(wrapper.text()).toContain('$100.00')

    await router.push('/activity')
    await flushPromises()
    expect(wrapper.text()).toContain('New transaction')

    await router.push('/review')
    await flushPromises()
    expect(wrapper.text()).toContain('All caught up')

    await router.push('/rules')
    await flushPromises()
    expect(wrapper.text()).toContain('New rule')
  })
})
