/**
 * Demo user session persistence
 */
import { beforeEach, describe, expect, it } from 'vitest'
import {
  DEMO_USER_STORAGE_KEY,
  __resetDemoUserSessionForTests,
  clearSelectedDemoUser,
  getSelectedDemoUserId,
  setSelectedDemoUserId,
} from './demoUserSession'

describe('demoUserSession', () => {
  beforeEach(() => {
    localStorage.clear()
    __resetDemoUserSessionForTests()
  })

  it('stores and reads the selected demo user id', () => {
    setSelectedDemoUserId(7)
    expect(localStorage.getItem(DEMO_USER_STORAGE_KEY)).toBe('7')
    expect(getSelectedDemoUserId()).toBe(7)
  })

  it('clears the selected demo user id', () => {
    setSelectedDemoUserId(3)
    clearSelectedDemoUser()
    expect(localStorage.getItem(DEMO_USER_STORAGE_KEY)).toBeNull()
    expect(getSelectedDemoUserId()).toBeNull()
  })

  it('survives a page-refresh style reload from localStorage', () => {
    localStorage.setItem(DEMO_USER_STORAGE_KEY, '12')
    __resetDemoUserSessionForTests()
    expect(getSelectedDemoUserId()).toBe(12)
  })

  it('ignores invalid stored demo user ids', () => {
    for (const raw of ['0', '-3', 'abc', '']) {
      localStorage.setItem(DEMO_USER_STORAGE_KEY, raw)
      __resetDemoUserSessionForTests()
      expect(getSelectedDemoUserId()).toBeNull()
    }
  })
})
