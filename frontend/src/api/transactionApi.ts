import type {
  Transaction,
  TransactionCollectionResponse,
  TransactionResourceResponse,
  UpdateTransactionPayload,
} from '../types/transaction'

/**
 * Fetch helpers for the transaction review API.
 * Uses relative /api URLs so Vite can proxy them to Laravel in development.
 */

async function readErrorMessage(response: Response): Promise<string> {
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

/** GET /api/transactions — unreviewed transactions, oldest first (once backend is done). */
export async function fetchTransactions(): Promise<Transaction[]> {
  const response = await fetch('/api/transactions')

  if (!response.ok) {
    throw new Error(await readErrorMessage(response))
  }

  const json = (await response.json()) as TransactionCollectionResponse
  return json.data
}

/** PATCH /api/transactions/{id} — categorize and mark reviewed. */
export async function updateTransaction(
  id: number,
  payload: UpdateTransactionPayload,
): Promise<Transaction> {
  const response = await fetch(`/api/transactions/${id}`, {
    method: 'PATCH',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: JSON.stringify(payload),
  })

  if (!response.ok) {
    throw new Error(await readErrorMessage(response))
  }

  const json = (await response.json()) as TransactionResourceResponse
  return json.data
}
