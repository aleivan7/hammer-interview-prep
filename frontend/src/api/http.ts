export async function readErrorMessage(response: Response): Promise<string> {
  try {
    const body = (await response.json()) as {
      message?: string
      errors?: Record<string, string[]>
    }

    if (body.errors) {
      const firstError = Object.values(body.errors).flat()[0]
      if (firstError) {
        return firstError
      }
    }

    if (body.message) {
      return body.message
    }
  } catch {
    // Response was not JSON; fall through to a generic message.
  }

  return `Request failed with status ${response.status}`
}

export async function apiFetch<T>(url: string, init?: RequestInit): Promise<T> {
  const response = await fetch(url, {
    ...init,
    headers: {
      Accept: 'application/json',
      ...(init?.body ? { 'Content-Type': 'application/json' } : {}),
      ...init?.headers,
    },
  })

  if (!response.ok) {
    throw new Error(await readErrorMessage(response))
  }

  if (response.status === 204) {
    return undefined as T
  }

  return (await response.json()) as T
}
