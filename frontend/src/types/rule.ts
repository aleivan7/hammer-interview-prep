import type { Bucket } from './bucket'
import type { CategorySummary } from './category'
import type { MerchantSummary } from './merchant'

export interface CategorizationRule {
  id: number
  name: string
  merchant_id: number | null
  /** Legacy free-text match; optional during structured migration. */
  merchant_contains?: string | null
  canonical_merchant?: MerchantSummary | null
  account_id: number | null
  amount_cents_min: number | null
  amount_cents_max: number | null
  category_id: number | null
  target_category?: CategorySummary | null
  target_bucket: Bucket
  /** Legacy subcategory label derived from category name. */
  target_subcategory?: string | null
  priority: number
  enabled: boolean
  auto_review: boolean
}

export interface StoreCategorizationRulePayload {
  name: string
  merchant_id: number
  category_id: number
  account_id?: number | null
  amount_cents_min?: number | null
  amount_cents_max?: number | null
  priority?: number
  enabled?: boolean
  auto_review?: boolean
}

export type UpdateCategorizationRulePayload = Partial<StoreCategorizationRulePayload>

export interface RuleResourceResponse {
  data: CategorizationRule
}

export interface RuleCollectionResponse {
  data: CategorizationRule[]
}
