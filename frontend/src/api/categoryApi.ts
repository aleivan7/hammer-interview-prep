import type {
  Category,
  CategoryCollectionResponse,
  CategoryResourceResponse,
  StoreCategoryPayload,
  UpdateCategoryPayload,
} from '../types/category'
import { apiFetch } from './http'

export async function fetchCategories(): Promise<Category[]> {
  const json = await apiFetch<CategoryCollectionResponse>('/api/categories')
  return json.data
}

export async function createCategory(payload: StoreCategoryPayload): Promise<Category> {
  const json = await apiFetch<CategoryResourceResponse>('/api/categories', {
    method: 'POST',
    body: JSON.stringify(payload),
  })
  return json.data
}

export async function updateCategory(
  id: number,
  payload: UpdateCategoryPayload,
): Promise<Category> {
  const json = await apiFetch<CategoryResourceResponse>(`/api/categories/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(payload),
  })
  return json.data
}

export async function archiveCategory(id: number): Promise<void> {
  await apiFetch<void>(`/api/categories/${id}`, { method: 'DELETE' })
}
