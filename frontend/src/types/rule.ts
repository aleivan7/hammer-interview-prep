import type { Bucket } from './bucket'

export interface CategorizationRule {
  id: number
  name: string
  merchant_contains: string
  account_id: number | null
  amount_cents_min: number | null
  amount_cents_max: number | null
  target_bucket: Bucket
  target_subcategory: string | null
  priority: number
  enabled: boolean
  auto_review: boolean
}

export interface StoreCategorizationRulePayload {
  name: string
  merchant_contains: string
  account_id?: number | null
  amount_cents_min?: number | null
  amount_cents_max?: number | null
  target_bucket: Bucket
  target_subcategory?: string | null
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
