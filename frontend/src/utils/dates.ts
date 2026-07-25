import type { Transaction } from '../types/transaction'

export function formatRelativeDay(isoDate: string): string {
  const date = new Date(`${isoDate}T12:00:00`)

  if (Number.isNaN(date.getTime())) {
    return isoDate
  }

  const today = new Date()
  today.setHours(12, 0, 0, 0)

  const yesterday = new Date(today)
  yesterday.setDate(today.getDate() - 1)

  const target = new Date(date)
  target.setHours(12, 0, 0, 0)

  if (target.getTime() === today.getTime()) {
    return 'Today'
  }

  if (target.getTime() === yesterday.getTime()) {
    return 'Yesterday'
  }

  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  }).format(date)
}

export interface DateGroup {
  date: string
  label: string
  transactions: Transaction[]
}

export function groupByDate(transactions: Transaction[]): DateGroup[] {
  const map = new Map<string, Transaction[]>()

  for (const transaction of transactions) {
    const existing = map.get(transaction.transaction_date)
    if (existing) {
      existing.push(transaction)
    } else {
      map.set(transaction.transaction_date, [transaction])
    }
  }

  return [...map.entries()]
    .sort(([a], [b]) => (a < b ? 1 : a > b ? -1 : 0))
    .map(([date, rows]) => ({
      date,
      label: formatRelativeDay(date),
      transactions: rows,
    }))
}
