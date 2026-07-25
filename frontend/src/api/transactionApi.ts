import type {
  StoreTransactionPayload,
  SuggestionResourceResponse,
  Transaction,
  TransactionCollectionResponse,
  TransactionResourceResponse,
  TransactionSuggestion,
  UpdateTransactionPayload,
} from '../types/transaction'
import { apiFetch } from './http'

export interface FetchTransactionsParams {
  unreviewedOnly?: boolean
  queue?: 'review'
  reviewed?: boolean
  bucket?: string
  accountId?: number | null
  search?: string
  paginate?: boolean
}

function toQuery(params: FetchTransactionsParams = {}): string {
  const query = new URLSearchParams()

  if (params.unreviewedOnly) {
    query.set('unreviewed_only', '1')
  }

  if (params.queue) {
    query.set('queue', params.queue)
  }

  if (params.reviewed !== undefined) {
    query.set('reviewed', String(params.reviewed))
  }

  if (params.bucket) {
    query.set('bucket', params.bucket)
  }

  if (params.accountId != null) {
    query.set('account_id', String(params.accountId))
  }

  if (params.search?.trim()) {
    query.set('search', params.search.trim())
  }

  if (params.paginate === false) {
    query.set('paginate', 'false')
  }

  const serialized = query.toString()
  return serialized ? `?${serialized}` : ''
}

/** GET /api/transactions — defaults to newest-first paginated list. */
export async function fetchTransactions(
  params: FetchTransactionsParams = {},
): Promise<Transaction[]> {
  const json = await apiFetch<TransactionCollectionResponse>(
    `/api/transactions${toQuery(params)}`,
  )
  return json.data
}

/** Review queue: unreviewed, oldest first. */
export async function fetchReviewQueue(): Promise<Transaction[]> {
  return fetchTransactions({ queue: 'review' })
}

export async function createTransaction(
  payload: StoreTransactionPayload,
): Promise<Transaction> {
  const json = await apiFetch<TransactionResourceResponse>('/api/transactions', {
    method: 'POST',
    body: JSON.stringify(payload),
  })
  return json.data
}

export async function updateTransaction(
  id: number,
  payload: UpdateTransactionPayload,
): Promise<Transaction> {
  const json = await apiFetch<TransactionResourceResponse>(`/api/transactions/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(payload),
  })
  return json.data
}

export async function undoTransactionReview(id: number): Promise<Transaction> {
  const json = await apiFetch<TransactionResourceResponse>(`/api/transactions/${id}/undo`, {
    method: 'POST',
  })
  return json.data
}

export async function fetchTransactionSuggestion(
  id: number,
): Promise<TransactionSuggestion> {
  const json = await apiFetch<SuggestionResourceResponse>(
    `/api/transactions/${id}/suggestion`,
  )
  return json.data
}
