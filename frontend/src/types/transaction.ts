import type { Bucket, TransactionKind } from './bucket'

export type TransactionCategory = Bucket

export interface TransactionAccountSummary {
  id: number
  name: string
  institution_name: string
}

export interface CanonicalMerchantSummary {
  id: number
  name: string
  normalized_name: string
  logo_key: string | null
}

export interface DetailedCategorySummary {
  id: number
  name: string
  bucket: Bucket
  is_system: boolean
  archived_at: string | null
}

export interface Transaction {
  id: number
  account_id: number | null
  account?: TransactionAccountSummary | null
  merchant: string
  raw_merchant_descriptor: string
  merchant_id: number | null
  canonical_merchant?: CanonicalMerchantSummary | null
  amount_cents: number
  /** Decimal amount serialized as a string, e.g. "84.23" */
  amount: string
  kind: TransactionKind
  bucket: Bucket | null
  subcategory: string | null
  category_id: number | null
  detailed_category?: DetailedCategorySummary | null
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
  bucket?: Bucket | null
  category?: Bucket | 'debt_savings'
  category_id?: number | null
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
  category_id?: number | null
  subcategory?: string | null
  notes?: string | null
  reviewed?: boolean
}

export interface TransactionSuggestion {
  bucket: Bucket | null
  subcategory: string | null
  category_id: number | null
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
