/**
 * Demo user composable: profile load, stale-session clear, and reset revision
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '../api/http'
import { fetchProfile, resetDemoProfile } from '../api/profileApi'
import {
  DEMO_USER_STORAGE_KEY,
  __resetDemoUserSessionForTests,
  getSelectedDemoUserId,
  setSelectedDemoUserId,
} from '../session/demoUserSession'
import type { DemoProfile } from '../types/demoUser'
import { __resetUseDemoUserForTests, useDemoUser } from './useDemoUser'

vi.mock('../api/profileApi', () => ({
  fetchProfile: vi.fn(),
  resetDemoProfile: vi.fn(),
}))

const profile: DemoProfile = {
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
  plan: null,
  accounts: [],
  financial_status_label: 'Balanced and on track',
}

beforeEach(() => {
  localStorage.clear()
  __resetDemoUserSessionForTests()
  __resetUseDemoUserForTests()
  vi.mocked(fetchProfile).mockReset()
  vi.mocked(resetDemoProfile).mockReset()
})

describe('useDemoUser', () => {
  it('returns null from ensureProfile when no demo user is selected', async () => {
    const { ensureProfile, profile: profileRef } = useDemoUser()

    await expect(ensureProfile()).resolves.toBeNull()
    expect(profileRef.value).toBeNull()
    expect(fetchProfile).not.toHaveBeenCalled()
  })

  it('loads and caches the selected demo profile', async () => {
    setSelectedDemoUserId(2)
    vi.mocked(fetchProfile).mockResolvedValue(profile)
    const { ensureProfile, profile: profileRef } = useDemoUser()

    await expect(ensureProfile()).resolves.toEqual(profile)
    await expect(ensureProfile()).resolves.toEqual(profile)

    expect(fetchProfile).toHaveBeenCalledTimes(1)
    expect(profileRef.value?.name).toBe('Jordan Lee')
  })

  it('clears a stale selected id when profile load reports demo_user_invalid', async () => {
    setSelectedDemoUserId(99)
    vi.mocked(fetchProfile).mockRejectedValue(
      new ApiError('The selected demo user is invalid.', 401, 'demo_user_invalid'),
    )
    const { ensureProfile, error } = useDemoUser()

    await expect(ensureProfile()).resolves.toBeNull()
    expect(getSelectedDemoUserId()).toBeNull()
    expect(localStorage.getItem(DEMO_USER_STORAGE_KEY)).toBeNull()
    expect(error.value).toContain('no longer available')
  })

  it('bumps the session revision after a successful demo data reset', async () => {
    setSelectedDemoUserId(2)
    vi.mocked(resetDemoProfile).mockResolvedValue({
      data: profile,
      message: 'Demo profile data restored to its original seeded state.',
    })
    const { resetCurrentDemoData, sessionRevision, profile: profileRef } = useDemoUser()
    const before = sessionRevision.value

    await expect(resetCurrentDemoData()).resolves.toEqual(profile)

    expect(sessionRevision.value).toBe(before + 1)
    expect(profileRef.value).toEqual(profile)
  })

  it('selectDemoUser stores the id and clears a previously cached profile', async () => {
    setSelectedDemoUserId(2)
    vi.mocked(fetchProfile).mockResolvedValue(profile)
    const { ensureProfile, selectDemoUser, profile: profileRef } = useDemoUser()
    await ensureProfile()

    selectDemoUser(1)

    expect(getSelectedDemoUserId()).toBe(1)
    expect(localStorage.getItem(DEMO_USER_STORAGE_KEY)).toBe('1')
    expect(profileRef.value).toBeNull()
  })
})
