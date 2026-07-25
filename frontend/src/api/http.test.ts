/**
 * Shared apiFetch demo-user header and stale-session handling
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import {
  DEMO_USER_STORAGE_KEY,
  __resetDemoUserSessionForTests,
  getSelectedDemoUserId,
  setSelectedDemoUserId,
} from '../session/demoUserSession'
import { ApiError, apiFetch } from './http'

describe('apiFetch', () => {
  beforeEach(() => {
    localStorage.clear()
    __resetDemoUserSessionForTests()
    vi.stubGlobal('fetch', vi.fn())
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('attaches X-Demo-User when a demo user is selected', async () => {
    setSelectedDemoUserId(5)
    vi.mocked(fetch).mockResolvedValue(
      new Response(JSON.stringify({ data: true }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    await apiFetch('/api/dashboard')

    const [, init] = vi.mocked(fetch).mock.calls[0]
    const headers = new Headers(init?.headers)
    expect(headers.get('X-Demo-User')).toBe('5')
    expect(headers.get('Accept')).toBe('application/json')
  })

  it('preserves caller headers while attaching X-Demo-User', async () => {
    setSelectedDemoUserId(5)
    vi.mocked(fetch).mockResolvedValue(
      new Response(JSON.stringify({ data: true }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    await apiFetch('/api/dashboard', {
      headers: {
        'X-Custom': 'yes',
      },
    })

    const headers = new Headers(vi.mocked(fetch).mock.calls[0][1]?.headers)
    expect(headers.get('X-Custom')).toBe('yes')
    expect(headers.get('X-Demo-User')).toBe('5')
  })

  it('skips X-Demo-User for public persona-list requests', async () => {
    setSelectedDemoUserId(5)
    vi.mocked(fetch).mockResolvedValue(
      new Response(JSON.stringify({ data: [] }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    await apiFetch('/api/demo-users', { skipDemoUserHeader: true })

    const headers = new Headers(vi.mocked(fetch).mock.calls[0][1]?.headers)
    expect(headers.has('X-Demo-User')).toBe(false)
  })

  it('clears a stale selected id on invalid demo-user responses', async () => {
    setSelectedDemoUserId(99)
    vi.mocked(fetch).mockResolvedValue(
      new Response(JSON.stringify({ message: 'Invalid', code: 'demo_user_invalid' }), {
        status: 401,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    await expect(apiFetch('/api/profile')).rejects.toBeInstanceOf(ApiError)
    expect(getSelectedDemoUserId()).toBeNull()
    expect(localStorage.getItem(DEMO_USER_STORAGE_KEY)).toBeNull()
  })

  it('clears the selected id when the API requires a demo user', async () => {
    setSelectedDemoUserId(4)
    vi.mocked(fetch).mockResolvedValue(
      new Response(JSON.stringify({ message: 'Required', code: 'demo_user_required' }), {
        status: 401,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    await expect(apiFetch('/api/dashboard')).rejects.toMatchObject({
      name: 'ApiError',
      status: 401,
      code: 'demo_user_required',
    })
    expect(getSelectedDemoUserId()).toBeNull()
    expect(localStorage.getItem(DEMO_USER_STORAGE_KEY)).toBeNull()
  })
})
