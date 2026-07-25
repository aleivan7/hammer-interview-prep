import type { Bucket } from '../types/bucket'
import type { Transaction } from '../types/transaction'
import { formatCents } from './money'

export function signedAmountCents(tx: Transaction): number {
  if (tx.kind === 'income' || tx.kind === 'refund') {
    return Math.abs(tx.amount_cents)
  }

  return -Math.abs(tx.amount_cents)
}

export function isCredit(tx: Transaction): boolean {
  return signedAmountCents(tx) > 0
}

export function formatSigned(cents: number): string {
  const absolute = formatCents(Math.abs(cents))
  if (cents > 0) {
    return `+${absolute}`
  }
  if (cents < 0) {
    return `-${absolute}`
  }
  return absolute
}

export interface BucketSpend {
  bucket: Bucket
  cents: number
  percent: number
}

export function aggregateBucketSpend(transactions: Transaction[]): BucketSpend[] {
  const totals: Record<Bucket, number> = { need: 0, want: 0, savings: 0 }

  for (const tx of transactions) {
    if (!tx.bucket || tx.kind === 'income' || tx.kind === 'refund') {
      continue
    }
    totals[tx.bucket] += Math.abs(tx.amount_cents)
  }

  const spend = totals.need + totals.want + totals.savings

  return (['need', 'want', 'savings'] as Bucket[]).map((bucket) => ({
    bucket,
    cents: totals[bucket],
    percent: spend > 0 ? Math.round((totals[bucket] * 100) / spend) : 0,
  }))
}

export function summarizeCashFlow(transactions: Transaction[]): {
  incomeCents: number
  expenseCents: number
  netCents: number
} {
  let incomeCents = 0
  let expenseCents = 0

  for (const tx of transactions) {
    const signed = signedAmountCents(tx)
    if (signed > 0) {
      incomeCents += signed
    } else {
      expenseCents += Math.abs(signed)
    }
  }

  return {
    incomeCents,
    expenseCents,
    netCents: incomeCents - expenseCents,
  }
}
