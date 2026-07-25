import type { Account, AccountCollectionResponse } from '../types/account'
import { apiFetch } from './http'

export async function fetchAccounts(): Promise<Account[]> {
  const json = await apiFetch<AccountCollectionResponse>('/api/accounts')
  return json.data
}
