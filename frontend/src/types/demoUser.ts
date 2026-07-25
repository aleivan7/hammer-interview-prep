import type { Account } from './account'
import type { FinancialPlanSummary } from './dashboard'

export type PersonaType = 'reckless' | 'average' | 'high_net_worth'

export interface DemoPersona {
  id: number
  name: string
  email: string
  persona_type: PersonaType
  persona_label: string
  description: string
  avatar_initials: string
  monthly_income_cents: number
  monthly_income: string
  account_count: number
  financial_status_label: string
}

export interface DemoPersonaListResponse {
  data: DemoPersona[]
}

export interface DemoProfile {
  id: number
  name: string
  email: string
  persona_type: PersonaType
  persona_label: string
  description: string
  member_since: string
  avatar_initials: string
  monthly_income_cents: number
  monthly_income: string
  total_balance_cents: number
  total_balance: string
  account_count: number
  plan: FinancialPlanSummary | null
  accounts: Account[]
  financial_status_label: string
}

export interface DemoProfileResponse {
  data: DemoProfile
}

export interface DemoProfileResetResponse {
  message: string
  data: DemoProfile
}

export interface DemoPersonaIdentity {
  id: number
  name: string
  email: string
  persona_type?: PersonaType | null
  persona_label?: string | null
  description?: string | null
  member_since: string
  avatar_initials?: string | null
}
