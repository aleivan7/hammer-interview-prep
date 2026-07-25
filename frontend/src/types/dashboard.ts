import type { Account } from './account'
import type { Bucket } from './bucket'
import type { Transaction } from './transaction'

export interface SafeToSpendBreakdown {
  available_cash_cents: number
  available_cash: string
  remaining_expected_income_cents: number
  remaining_expected_income: string
  upcoming_essential_bills_cents: number
  upcoming_essential_bills: string
  remaining_savings_target_cents: number
  remaining_savings_target: string
  safety_buffer_cents: number
  safety_buffer: string
}

export interface SafeToSpend {
  safe_to_spend_cents: number
  amount: string
  effective_on: string
  period: string
  breakdown: SafeToSpendBreakdown
  bucket_actuals: Record<Bucket, number>
  bucket_targets: Record<Bucket, number>
  unusual_alerts: Array<{
    merchant: string
    amount: string
    message: string
  }>
}

export interface FinancialPlanSummary {
  needs_percent: number
  wants_percent: number
  savings_percent: number
  safety_buffer_cents: number
  safety_buffer: string
  monthly_income_cents: number
  monthly_income: string
}

export interface PlannedCashFlow {
  id: number
  name: string
  amount_cents: number
  amount: string
  kind: 'income' | 'bill'
  due_on: string
  is_essential: boolean
  bucket: Bucket | null
}

export interface DashboardData {
  persona: {
    name: string
    email: string
    member_since: string
  }
  safe_to_spend: SafeToSpend
  plan: FinancialPlanSummary | null
  cash_flows: PlannedCashFlow[]
  accounts: Account[]
  recent_transactions: Transaction[]
  unreviewed_count: number
}

export interface DashboardResourceResponse {
  data: DashboardData
}
