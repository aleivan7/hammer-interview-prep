import { shallowRef } from 'vue'
import { fetchProfile, resetDemoProfile } from '../api/profileApi'
import { ApiError } from '../api/http'
import {
  bumpSessionRevision,
  clearSelectedDemoUser,
  setDemoProfile,
  setSelectedDemoUserId,
  useDemoUserSessionState,
} from '../session/demoUserSession'
import type { DemoProfile } from '../types/demoUser'

const loading = shallowRef(false)
const error = shallowRef<string | null>(null)
let profileRequest: Promise<DemoProfile | null> | null = null
let validatedForId: number | null = null

export function useDemoUser() {
  const session = useDemoUserSessionState()

  async function ensureProfile(options: { force?: boolean } = {}): Promise<DemoProfile | null> {
    const id = session.selectedUserId.value
    if (id === null) {
      setDemoProfile(null)
      validatedForId = null
      return null
    }

    if (!options.force && session.profile.value && validatedForId === id) {
      return session.profile.value
    }

    if (!options.force && profileRequest && validatedForId === id) {
      return profileRequest
    }

    loading.value = true
    error.value = null
    validatedForId = id

    profileRequest = (async () => {
      try {
        const next = await fetchProfile()
        setDemoProfile(next)
        return next
      } catch (err) {
        setDemoProfile(null)
        if (err instanceof ApiError && (err.code === 'demo_user_invalid' || err.code === 'demo_user_required')) {
          clearSelectedDemoUser()
          validatedForId = null
          error.value = 'That demo profile is no longer available. Choose another persona.'
          return null
        }

        error.value = err instanceof Error ? err.message : 'Failed to load demo profile.'
        throw err
      } finally {
        loading.value = false
        profileRequest = null
      }
    })()

    return profileRequest
  }

  function selectDemoUser(id: number): void {
    setSelectedDemoUserId(id)
    setDemoProfile(null)
    validatedForId = null
    error.value = null
  }

  function switchDemoUser(): void {
    clearSelectedDemoUser()
    validatedForId = null
    error.value = null
  }

  async function resetCurrentDemoData(): Promise<DemoProfile> {
    loading.value = true
    error.value = null

    try {
      const response = await resetDemoProfile()
      setDemoProfile(response.data)
      bumpSessionRevision()
      validatedForId = response.data.id
      return response.data
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Failed to reset demo data.'
      throw err
    } finally {
      loading.value = false
    }
  }

  return {
    ...session,
    loading,
    error,
    ensureProfile,
    selectDemoUser,
    switchDemoUser,
    resetCurrentDemoData,
  }
}

/** Test helper — resets module-level profile request state. */
export function __resetUseDemoUserForTests(): void {
  loading.value = false
  error.value = null
  profileRequest = null
  validatedForId = null
}
