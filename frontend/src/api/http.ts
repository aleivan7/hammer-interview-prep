import {
  clearSelectedDemoUser,
  getSelectedDemoUserId,
} from '../session/demoUserSession'

export class ApiError extends Error {
  readonly status: number
  readonly code: string | null

  constructor(message: string, status: number, code: string | null = null) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.code = code
  }
}

export async function readErrorPayload(response: Response): Promise<{
  message: string
  code: string | null
}> {
  try {
    const body = (await response.json()) as {
      message?: string
      code?: string
      errors?: Record<string, string[]>
    }

    if (body.errors) {
      const firstError = Object.values(body.errors).flat()[0]
      if (firstError) {
        return { message: firstError, code: body.code ?? null }
      }
    }

    if (body.message) {
      return { message: body.message, code: body.code ?? null }
    }
  } catch {
    // Response was not JSON; fall through to a generic message.
  }

  return {
    message: `Request failed with status ${response.status}`,
    code: null,
  }
}

export type ApiFetchOptions = RequestInit & {
  /** Skip attaching X-Demo-User (used for public persona listing). */
  skipDemoUserHeader?: boolean
}

function mergeHeaders(init?: RequestInit, demoUserId?: number | null): Headers {
  const headers = new Headers(init?.headers)

  if (!headers.has('Accept')) {
    headers.set('Accept', 'application/json')
  }

  if (init?.body && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json')
  }

  if (demoUserId !== null && demoUserId !== undefined && !headers.has('X-Demo-User')) {
    headers.set('X-Demo-User', String(demoUserId))
  }

  return headers
}

export async function apiFetch<T>(url: string, init?: ApiFetchOptions): Promise<T> {
  const { skipDemoUserHeader = false, ...requestInit } = init ?? {}
  const demoUserId = skipDemoUserHeader ? null : getSelectedDemoUserId()

  const response = await fetch(url, {
    ...requestInit,
    headers: mergeHeaders(requestInit, demoUserId),
  })

  if (!response.ok) {
    const { message, code } = await readErrorPayload(response)

    if (
      response.status === 401 &&
      (code === 'demo_user_invalid' || code === 'demo_user_required')
    ) {
      clearSelectedDemoUser()
    }

    throw new ApiError(message, response.status, code)
  }

  if (response.status === 204) {
    return undefined as T
  }

  return (await response.json()) as T
}
