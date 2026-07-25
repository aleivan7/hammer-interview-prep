import type { DemoPersona, DemoPersonaListResponse } from '../types/demoUser'
import { apiFetch } from './http'

export async function fetchDemoUsers(): Promise<DemoPersona[]> {
  const json = await apiFetch<DemoPersonaListResponse>('/api/demo-users', {
    skipDemoUserHeader: true,
  })
  return json.data
}
