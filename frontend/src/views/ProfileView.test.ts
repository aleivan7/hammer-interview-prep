/**
 * Profile screen: selected user info, switch, and reset confirmation
 */
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { fetchProfile, resetDemoProfile } from '../api/profileApi'
import {
  DEMO_USER_STORAGE_KEY,
  __resetDemoUserSessionForTests,
  getSelectedDemoUserId,
  setDemoProfile,
  setSelectedDemoUserId,
} from '../session/demoUserSession'
import { routes } from '../router'
import ProfileView from './ProfileView.vue'
import LoginView from './LoginView.vue'

vi.mock('../api/profileApi', () => ({
  fetchProfile: vi.fn(),
  resetDemoProfile: vi.fn(),
}))

vi.mock('../api/demoUserApi', () => ({
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
  total_balance_cents: 442000,
  total_balance: '4420.00',
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
  accounts: [
    {
      id: 1,
      institution_name: 'First Horizon',
      name: 'Everyday Checking',
      mask: '4821',
      type: 'checking',
      balance_cents: 184350,
      balance: '1843.50',
      sync_status: 'healthy' as const,
      logo_key: 'first-horizon',
      sort_order: 1,
    },
  ],
  financial_status_label: 'Balanced and on track',
}

describe('ProfileView', () => {
  beforeEach(() => {
    localStorage.clear()
    __resetDemoUserSessionForTests()
    setSelectedDemoUserId(2)
    setDemoProfile(profile)
    vi.mocked(fetchProfile).mockResolvedValue(profile)
    vi.mocked(resetDemoProfile).mockResolvedValue({
      message: 'Demo profile data restored to its original seeded state.',
      data: profile,
    })
  })

  it('renders selected-user information', async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes,
    })
    router.push('/profile')
    await router.isReady()

    const wrapper = mount(ProfileView, {
      global: { plugins: [router] },
    })
    await flushPromises()

    expect(wrapper.text()).toContain('Jordan Lee')
    expect(wrapper.text()).toContain('jordan.lee@clearspend.demo')
    expect(wrapper.text()).toContain('Average Spender')
    expect(wrapper.text()).toContain('Everyday Checking')
    expect(wrapper.text()).toContain('50%')
  })

  it('switching users clears the selection and navigates to login', async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [
        ...routes.filter((route) => route.name !== 'login'),
        {
          path: '/login',
          name: 'login',
          component: LoginView,
          meta: { title: 'Choose a demo profile', public: true },
        },
      ],
    })
    router.push('/profile')
    await router.isReady()

    const wrapper = mount(ProfileView, {
      global: { plugins: [router] },
    })
    await flushPromises()

    await wrapper.get('button.btn-ghost').trigger('click')
    await flushPromises()

    expect(getSelectedDemoUserId()).toBeNull()
    expect(localStorage.getItem(DEMO_USER_STORAGE_KEY)).toBeNull()
    expect(router.currentRoute.value.name).toBe('login')
  })

  it('requires confirmation before calling reset', async () => {
    const router = createRouter({
      history: createMemoryHistory(),
      routes,
    })
    router.push('/profile')
    await router.isReady()

    const wrapper = mount(ProfileView, {
      global: { plugins: [router] },
    })
    await flushPromises()

    await wrapper.get('button.btn-primary').trigger('click')
    expect(wrapper.text()).toContain('Reset this demo profile?')
    expect(resetDemoProfile).not.toHaveBeenCalled()

    await wrapper.get('[role="dialog"] button.btn-primary').trigger('click')
    await flushPromises()

    expect(resetDemoProfile).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain('Demo data restored for this fictional profile.')
  })
})
