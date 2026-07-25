import type { Bucket, TransactionKind } from './bucket'

export type TransactionCategory = Bucket

export interface TransactionAccountSummary {
  id: number
  name: string
  institution_name: string
}

export interface Transaction {
  id: number
  account_id: number | null
  account?: TransactionAccountSummary | null
  merchant: string
  amount_cents: number
  /** Decimal amount serialized as a string, e.g. "84.23" */
  amount: string
  kind: TransactionKind
  bucket: Bucket | null
  subcategory: string | null
  /** Legacy alias of bucket for transitional clients */
  category: Bucket | null
  transaction_date: string
  reviewed: boolean
  review_source: string | null
  confidence: number | null
  review_explanation: string | null
  notes: string | null
}

export interface UpdateTransactionPayload {
  merchant?: string
  amount_cents?: number
  kind?: TransactionKind
  bucket?: Bucket
  category?: Bucket | 'debt_savings'
  subcategory?: string | null
  transaction_date?: string
  account_id?: number | null
  notes?: string | null
  reviewed?: boolean
}

export interface StoreTransactionPayload {
  merchant: string
  amount_cents: number
  kind: TransactionKind
  transaction_date: string
  account_id?: number | null
  bucket?: Bucket | null
  subcategory?: string | null
  notes?: string | null
  reviewed?: boolean
}

export interface TransactionSuggestion {
  bucket: Bucket | null
  subcategory: string | null
  confidence: number
  source: string
  explanation: string
  auto_review: boolean
}

export interface TransactionResourceResponse {
  data: Transaction
}

export interface TransactionCollectionResponse {
  data: Transaction[]
  links?: unknown
  meta?: unknown
}

export interface SuggestionResourceResponse {
  data: TransactionSuggestion
}
