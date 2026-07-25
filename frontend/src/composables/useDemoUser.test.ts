/**
 * Demo user composable request racing
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fetchProfile } from '../api/profileApi'
import {
  __resetDemoUserSessionForTests,
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

describe('useDemoUser', () => {
  beforeEach(() => {
    localStorage.clear()
    __resetDemoUserSessionForTests()
    __resetUseDemoUserForTests()
    vi.mocked(fetchProfile).mockReset()
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

    const { ensureProfile, selectDemoUser, profile } = useDemoUser()

    selectDemoUser(1)
    const firstRequest = ensureProfile({ force: true })

    selectDemoUser(2)
    const secondRequest = ensureProfile({ force: true })

    await expect(secondRequest).resolves.toEqual(second)
    expect(profile.value).toEqual(second)

    resolveFirst?.(first)
    await expect(firstRequest).resolves.toBeNull()
    expect(profile.value).toEqual(second)
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
})
