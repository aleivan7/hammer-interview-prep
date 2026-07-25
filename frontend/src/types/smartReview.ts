import type { Bucket } from './bucket'

export interface SmartReviewApplied {
  id: number
  merchant: string
  bucket: Bucket | null
  subcategory: string | null
  confidence: number | null
  explanation: string | null
  source: string | null
}

export interface SmartReviewSkipped {
  id: number
  merchant: string
  confidence: number
  explanation: string
  suggested_bucket: Bucket | null
  suggested_subcategory: string | null
}

export interface SmartReviewResult {
  applied: SmartReviewApplied[]
  skipped: SmartReviewSkipped[]
  applied_count: number
  skipped_count: number
  batch_key: string
}

export interface SmartReviewResourceResponse {
  data: SmartReviewResult
}
