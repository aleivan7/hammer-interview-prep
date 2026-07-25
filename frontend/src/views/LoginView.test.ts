/**
 * Login/persona selection
 */
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { fetchDemoUsers } from '../api/demoUserApi'
import { fetchProfile } from '../api/profileApi'
import {
  DEMO_USER_STORAGE_KEY,
  __resetDemoUserSessionForTests,
  getSelectedDemoUserId,
} from '../session/demoUserSession'
import { routes } from '../router'
import LoginView from './LoginView.vue'
import OverviewView from './OverviewView.vue'

vi.mock('../api/demoUserApi', () => ({
  fetchDemoUsers: vi.fn(),
}))

vi.mock('../api/profileApi', () => ({
  fetchProfile: vi.fn(),
  resetDemoProfile: vi.fn(),
}))

vi.mock('../api/dashboardApi', () => ({
  fetchDashboard: vi.fn().mockResolvedValue({
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
    plan: null,
    cash_flows: [],
    accounts: [],
    recent_transactions: [],
    unreviewed_count: 0,
  }),
}))

const personas = [
  {
    id: 1,
    name: 'Alex Rivera',
    email: 'alex.rivera@clearspend.demo',
    persona_type: 'reckless' as const,
    persona_label: 'Reckless Spender',
    description: 'Pressure persona',
    avatar_initials: 'AR',
    monthly_income_cents: 450000,
    monthly_income: '4500.00',
    account_count: 3,
    financial_status_label: 'Under financial pressure',
  },
  {
    id: 2,
    name: 'Jordan Lee',
    email: 'jordan.lee@clearspend.demo',
    persona_type: 'average' as const,
    persona_label: 'Average Spender',
    description: 'Balanced persona',
    avatar_initials: 'JL',
    monthly_income_cents: 520000,
    monthly_income: '5200.00',
    account_count: 3,
    financial_status_label: 'Balanced and on track',
  },
  {
    id: 3,
    name: 'Morgan Chen',
    email: 'morgan.chen@clearspend.demo',
    persona_type: 'high_net_worth' as const,
    persona_label: 'High-Net-Worth Individual',
    description: 'Strong savings',
    avatar_initials: 'MC',
    monthly_income_cents: 1850000,
    monthly_income: '18500.00',
    account_count: 5,
    financial_status_label: 'Strong savings progress',
  },
]

describe('LoginView', () => {
  beforeEach(() => {
    localStorage.clear()
    __resetDemoUserSessionForTests()
    vi.mocked(fetchDemoUsers).mockResolvedValue(personas)
    vi.mocked(fetchProfile).mockResolvedValue({
      id: 2,
      name: 'Jordan Lee',
      email: 'jordan.lee@clearspend.demo',
      persona_type: 'average',
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
    })
  })

  it('renders three personas', async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes,
    })
    router.push('/login')
    await router.isReady()

    const wrapper = mount(LoginView, {
      global: { plugins: [router] },
    })
    await flushPromises()

    expect(wrapper.text()).toContain('Choose a demo profile')
    expect(wrapper.text()).toContain('Alex Rivera')
    expect(wrapper.text()).toContain('Jordan Lee')
    expect(wrapper.text()).toContain('Morgan Chen')
    expect(wrapper.text()).toContain('Continue as Jordan')
  })

  it('stores the selected id and navigates to Overview', async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        ...routes.filter((route) => route.name !== 'overview'),
        { path: '/', name: 'overview', component: OverviewView, meta: { title: 'Overview', requiresDemoUser: true } },
      ],
    })
    router.push('/login')
    await router.isReady()

    const wrapper = mount(LoginView, {
      global: { plugins: [router] },
    })
    await flushPromises()

    const buttons = wrapper.findAll('button').filter((button) => button.text().includes('Continue as Jordan'))
    await buttons[0].trigger('click')
    await flushPromises()

    expect(getSelectedDemoUserId()).toBe(2)
    expect(localStorage.getItem(DEMO_USER_STORAGE_KEY)).toBe('2')
    expect(router.currentRoute.value.name).toBe('overview')
  })

  it('honors a safe redirect query after persona selection', async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes,
    })
    await router.push({ name: 'login', query: { redirect: '/activity' } })
    await router.isReady()

    const wrapper = mount(LoginView, {
      global: { plugins: [router] },
    })
    await flushPromises()

    const buttons = wrapper.findAll('button').filter((button) => button.text().includes('Continue as Jordan'))
    await buttons[0].trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.fullPath).toBe('/activity')
  })
})
