import type { Merchant, MerchantCollectionResponse } from '../types/merchant'
import { apiFetch } from './http'

export async function fetchMerchants(search?: string): Promise<Merchant[]> {
  const query = new URLSearchParams()
  if (search?.trim()) {
    query.set('search', search.trim())
  }

  const suffix = query.toString() ? `?${query.toString()}` : ''
  const json = await apiFetch<MerchantCollectionResponse>(`/api/merchants${suffix}`)
  return json.data
}
