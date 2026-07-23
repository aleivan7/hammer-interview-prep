/**
 * Allowed category values stored by the API.
 * The UI may show friendlier labels for these same values.
 */
export type TransactionCategory = 'need' | 'want' | 'debt_savings'

/**
 * One transaction as returned by Laravel's TransactionResource.
 */
export interface Transaction {
  id: number
  merchant: string
  /** Decimal amount serialized as a string, e.g. "84.23" */
  amount: string
  category: TransactionCategory | null
  /** ISO date string, e.g. "2026-07-20" */
  transaction_date: string
  /** true when reviewed_at is set on the backend */
  reviewed: boolean
}

/** Body for PATCH /api/transactions/{id} */
export interface UpdateTransactionPayload {
  category: TransactionCategory
  reviewed: boolean
}

/** Laravel single-resource response: { data: Transaction } */
export interface TransactionResourceResponse {
  data: Transaction
}

/** Laravel resource collection response: { data: Transaction[] } */
export interface TransactionCollectionResponse {
  data: Transaction[]
}
