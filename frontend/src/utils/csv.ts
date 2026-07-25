import type { Transaction } from '../types/transaction'
import { BUCKET_LABELS } from '../types/bucket'

function escapeCell(value: string): string {
  if (/[",\n]/.test(value)) {
    return `"${value.replaceAll('"', '""')}"`
  }
  return value
}

export function downloadTransactionsCsv(transactions: Transaction[]): void {
  const header = ['Date', 'Merchant', 'Kind', 'Bucket', 'Amount', 'Reviewed', 'Account']
  const rows = transactions.map((tx) => [
    tx.transaction_date,
    tx.merchant,
    tx.kind,
    tx.bucket ? BUCKET_LABELS[tx.bucket] : '',
    tx.amount,
    tx.reviewed ? 'yes' : 'no',
    tx.account?.name ?? '',
  ])

  const csv = [header, ...rows]
    .map((row) => row.map((cell) => escapeCell(String(cell))).join(','))
    .join('\n')

  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = `clearspend-transactions-${new Date().toISOString().slice(0, 10)}.csv`
  anchor.click()
  URL.revokeObjectURL(url)
}
