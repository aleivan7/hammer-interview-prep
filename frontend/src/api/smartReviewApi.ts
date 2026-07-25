import type { SmartReviewResourceResponse, SmartReviewResult } from '../types/smartReview'
import { apiFetch } from './http'

export async function runSmartReview(batchKey?: string): Promise<SmartReviewResult> {
  const json = await apiFetch<SmartReviewResourceResponse>('/api/smart-review', {
    method: 'POST',
    body: JSON.stringify(batchKey ? { batch_key: batchKey } : {}),
  })
  return json.data
}
