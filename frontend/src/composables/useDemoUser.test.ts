/**
 * Demo user composable: profile load, stale-session clear, reset revision, and request racing
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '../api/http'
import { fetchProfile, resetDemoProfile } from '../api/profileApi'
import {
  DEMO_USER_STORAGE_KEY,
  __resetDemoUserSessionForTests,
  getSelectedDemoUserId,
  setDemoProfile,
  setSelectedDemoUserId,
} from '../session/demoUserSession'
import type { DemoProfile } from '../types/demoUser'
import { __resetUseDemoUserForTests, useDemoUser } from './useDemoUser'

vi.mock('../api/profileApi', () => ({
  fetchProfile: vi.fn(),
  resetDemoProfile: vi.fn(),
}))

function makeProfile(id: number, name: string): DemoProfile {
  return {
    id,
    name,
    email: `${name.toLowerCase().replace(/\s+/g, '.')}@clearspend.demo`,
    persona_type: 'average',
    persona_label: 'Average Spender',
    description: 'Test persona',
    member_since: '2026-01-01',
    avatar_initials: 'TP',
    monthly_income_cents: 500000,
    monthly_income: '$5,000.00',
    total_balance_cents: 100000,
    total_balance: '$1,000.00',
    account_count: 1,
    plan: null,
    accounts: [],
    financial_status_label: 'On track',
  }
}

const profile = makeProfile(2, 'Jordan Lee')

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

  it('returns the cached profile when already validated for the selected user', async () => {
    const cached = makeProfile(3, 'Sam Chen')
    setSelectedDemoUserId(3)
    setDemoProfile(cached)

    const { ensureProfile } = useDemoUser()
    // Seed validatedForId by forcing one successful fetch first.
    vi.mocked(fetchProfile).mockResolvedValueOnce(cached)
    await ensureProfile({ force: true })

    vi.mocked(fetchProfile).mockClear()
    await expect(ensureProfile()).resolves.toEqual(cached)
    expect(fetchProfile).not.toHaveBeenCalled()
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

  it('ignores a stale profile response after switching personas', async () => {
    const first = makeProfile(1, 'Jordan Lee')
    const second = makeProfile(2, 'Alex Rivera')

    let resolveFirst: ((value: DemoProfile) => void) | undefined
    vi.mocked(fetchProfile)
      .mockImplementationOnce(
        () =>
          new Promise<DemoProfile>((resolve) => {
            resolveFirst = resolve
          }),
      )
      .mockResolvedValueOnce(second)

    const { ensureProfile, selectDemoUser, profile: profileRef } = useDemoUser()

    selectDemoUser(1)
    const firstRequest = ensureProfile({ force: true })

    selectDemoUser(2)
    const secondRequest = ensureProfile({ force: true })

    await expect(secondRequest).resolves.toEqual(second)
    expect(profileRef.value).toEqual(second)

    resolveFirst?.(first)
    await expect(firstRequest).resolves.toBeNull()
    expect(profileRef.value).toEqual(second)
  })
})
