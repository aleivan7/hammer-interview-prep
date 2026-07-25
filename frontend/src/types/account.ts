export type AccountSyncStatus = 'healthy' | 'error' | 'pending'

export interface Account {
  id: number
  institution_name: string
  name: string
  mask: string
  type: string
  balance_cents: number
  balance: string
  sync_status: AccountSyncStatus
  logo_key: string
  sort_order: number
}

export interface AccountCollectionResponse {
  data: Account[]
}
