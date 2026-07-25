import type {
  CategorizationRule,
  RuleCollectionResponse,
  RuleResourceResponse,
  StoreCategorizationRulePayload,
  UpdateCategorizationRulePayload,
} from '../types/rule'
import { apiFetch } from './http'

export async function fetchRules(): Promise<CategorizationRule[]> {
  const json = await apiFetch<RuleCollectionResponse>('/api/rules')
  return json.data
}

export async function createRule(
  payload: StoreCategorizationRulePayload,
): Promise<CategorizationRule> {
  const json = await apiFetch<RuleResourceResponse>('/api/rules', {
    method: 'POST',
    body: JSON.stringify(payload),
  })
  return json.data
}

export async function updateRule(
  id: number,
  payload: UpdateCategorizationRulePayload,
): Promise<CategorizationRule> {
  const json = await apiFetch<RuleResourceResponse>(`/api/rules/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(payload),
  })
  return json.data
}

export async function deleteRule(id: number): Promise<void> {
  await apiFetch<void>(`/api/rules/${id}`, { method: 'DELETE' })
}
