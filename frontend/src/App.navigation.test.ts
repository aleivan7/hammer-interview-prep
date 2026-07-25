/**
 * App shell navigation and demo-user route protection
 */
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
// createMemoryHistory used by createAppRouter in tests
import App from './App.vue'
import { fetchDashboard } from './api/dashboardApi'
import { fetchAccounts } from './api/accountApi'
import { fetchProfile } from './api/profileApi'
import { fetchReviewQueue, fetchTransactionSuggestion, fetchTransactions } from './api/transactionApi'
import { fetchRules } from './api/rulesApi'
import { createAppRouter, routes } from './router'
import {
  DEMO_USER_STORAGE_KEY,
  __resetDemoUserSessionForTests,
  clearSelectedDemoUser,
  setSelectedDemoUserId,
} from './session/demoUserSession'

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

vi.mock('./api/profileApi', () => ({
  fetchProfile: vi.fn(),
  resetDemoProfile: vi.fn(),
}))

vi.mock('./api/demoUserApi', () => ({
  fetchDemoUsers: vi.fn().mockResolvedValue([]),
}))

const profile = {
  id: 2,
  name: 'Jordan Lee',
  email: 'jordan.lee@clearspend.demo',
  persona_type: 'average' as const,
  persona_label: 'Average Spender',
  description: 'Balanced persona',
  member_since: '2026-01-01',
  avatar_initials: 'JL',
  monthly_income_cents: 520000,
  monthly_income: '5200.00',
  total_balance_cents: 100000,
  total_balance: '1000.00',
  account_count: 3,
  plan: {
    needs_percent: 50,
    wants_percent: 30,
    savings_percent: 20,
    safety_buffer_cents: 25000,
    safety_buffer: '250.00',
    monthly_income_cents: 520000,
    monthly_income: '5200.00',
  },
  accounts: [],
  financial_status_label: 'Balanced and on track',
}

beforeEach(() => {
  localStorage.clear()
  __resetDemoUserSessionForTests()
  setSelectedDemoUserId(2)

  vi.mocked(fetchProfile).mockResolvedValue(profile)
  vi.mocked(fetchDashboard).mockResolvedValue({
    persona: {
      id: 2,
      name: 'Jordan Lee',
      email: 'jordan.lee@clearspend.demo',
      member_since: '2026-01-01',
      avatar_initials: 'JL',
      persona_label: 'Average Spender',
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
  it('redirects an unauthenticated visitor to /login', async () => {
    clearSelectedDemoUser()
    const router = createAppRouter(createMemoryHistory())
    await router.push('/')
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('login')
  })

  it('clears a stale selected id and redirects to login', async () => {
    const { ApiError } = await import('./api/http')
    vi.mocked(fetchProfile).mockRejectedValue(
      new ApiError('The selected demo user is invalid.', 401, 'demo_user_invalid'),
    )

    const router = createAppRouter(createMemoryHistory())
    await router.push('/')
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('login')
    expect(localStorage.getItem(DEMO_USER_STORAGE_KEY)).toBeNull()
  })

  it('mounts overview, shows the selected persona, and navigates primary routes', async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes,
    })
    router.beforeEach(async (to) => {
      if (to.meta.public) {
        return true
      }
      await fetchProfile()
      return true
    })

    router.push('/')
    await router.isReady()

    const wrapper = mount(App, {
      global: {
        plugins: [router],
      },
    })

    await flushPromises()
    expect(wrapper.text()).toContain('ClearSpend')
    expect(wrapper.text()).toContain('Jordan Lee')
    expect(wrapper.text()).toContain('Average Spender')
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

    await router.push('/profile')
    await flushPromises()
    expect(wrapper.text()).toContain('Switch demo user')
  })
})
