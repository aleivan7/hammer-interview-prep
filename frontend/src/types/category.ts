import type { Bucket } from './bucket'

export interface Category {
  id: number
  user_id: number | null
  bucket: Bucket
  name: string
  normalized_name: string
  sort_order: number
  is_system: boolean
  archived_at: string | null
}

export interface CategorySummary {
  id: number
  name: string
  bucket: Bucket
  is_system: boolean
  archived_at: string | null
}

export interface StoreCategoryPayload {
  name: string
  bucket: Bucket
}

export interface UpdateCategoryPayload {
  name?: string
  bucket?: Bucket
  archived?: boolean
}

export interface CategoryResourceResponse {
  data: Category
}

export interface CategoryCollectionResponse {
  data: Category[]
}
