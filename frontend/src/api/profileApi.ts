import type {
  DemoProfile,
  DemoProfileResetResponse,
  DemoProfileResponse,
} from '../types/demoUser'
import { apiFetch } from './http'

export async function fetchProfile(): Promise<DemoProfile> {
  const json = await apiFetch<DemoProfileResponse>('/api/profile')
  return json.data
}

export async function resetDemoProfile(): Promise<DemoProfileResetResponse> {
  return apiFetch<DemoProfileResetResponse>('/api/profile/reset', {
    method: 'POST',
  })
}
