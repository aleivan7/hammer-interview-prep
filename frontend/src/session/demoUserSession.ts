import { computed, shallowRef } from 'vue'
import type { DemoProfile } from '../types/demoUser'

export const DEMO_USER_STORAGE_KEY = 'clearspend_demo_user_id'

const selectedUserId = shallowRef<number | null>(readStoredUserId())
const profile = shallowRef<DemoProfile | null>(null)
const sessionRevision = shallowRef(0)

function readStoredUserId(): number | null {
  try {
    const raw = localStorage.getItem(DEMO_USER_STORAGE_KEY)
    if (!raw) {
      return null
    }

    const parsed = Number.parseInt(raw, 10)
    return Number.isInteger(parsed) && parsed > 0 ? parsed : null
  } catch {
    return null
  }
}

export function getSelectedDemoUserId(): number | null {
  return selectedUserId.value
}

export function setSelectedDemoUserId(id: number): void {
  selectedUserId.value = id
  localStorage.setItem(DEMO_USER_STORAGE_KEY, String(id))
  sessionRevision.value += 1
}

export function clearSelectedDemoUser(): void {
  selectedUserId.value = null
  profile.value = null
  localStorage.removeItem(DEMO_USER_STORAGE_KEY)
  sessionRevision.value += 1
}

export function setDemoProfile(next: DemoProfile | null): void {
  profile.value = next
}

export function bumpSessionRevision(): void {
  sessionRevision.value += 1
}

export function useDemoUserSessionState() {
  return {
    selectedUserId,
    profile,
    sessionRevision,
    hasSelectedUser: computed(() => selectedUserId.value !== null),
  }
}

/** Test helper — resets in-memory session state without touching localStorage. */
export function __resetDemoUserSessionForTests(): void {
  selectedUserId.value = readStoredUserId()
  profile.value = null
  sessionRevision.value = 0
}
