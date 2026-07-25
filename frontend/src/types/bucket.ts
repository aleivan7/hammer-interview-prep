export type Bucket = 'need' | 'want' | 'savings'

export type TransactionKind = 'expense' | 'income' | 'transfer' | 'refund'

export const BUCKET_LABELS: Record<Bucket, string> = {
  need: 'Needs',
  want: 'Wants',
  savings: 'Savings',
}
