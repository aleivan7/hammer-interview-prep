import type { DashboardData, DashboardResourceResponse } from '../types/dashboard'
import { apiFetch } from './http'

export async function fetchDashboard(period?: string): Promise<DashboardData> {
  const query = period ? `?period=${encodeURIComponent(period)}` : ''
  const json = await apiFetch<DashboardResourceResponse>(`/api/dashboard${query}`)
  return json.data
}
