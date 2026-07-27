export interface MerchantAliasExample {
  pattern: string
  match_strategy: string
  priority: number
  enabled: boolean
}

export interface Merchant {
  id: number
  name: string
  normalized_name: string
  logo_key: string | null
  example_descriptors: MerchantAliasExample[]
}

export interface MerchantSummary {
  id: number
  name: string
  normalized_name: string
  logo_key: string | null
}

export interface MerchantResourceResponse {
  data: Merchant
}

export interface MerchantCollectionResponse {
  data: Merchant[]
}
