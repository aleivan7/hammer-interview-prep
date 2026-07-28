import type { Bucket } from '../types/bucket'
import type { Transaction } from '../types/transaction'
import { formatCents } from './money'

/** Prefer canonical merchant when present; fall back to legacy merchant. */
export function displayMerchantName(tx: Transaction): string {
  return tx.canonical_merchant?.name ?? tx.merchant
}

export function rawMerchantDescriptor(tx: Transaction): string {
  return tx.raw_merchant_descriptor || tx.merchant
}

export function hasDistinctRawDescriptor(tx: Transaction): boolean {
  const display = displayMerchantName(tx)
  const raw = rawMerchantDescriptor(tx)
  return raw.trim().toLowerCase() !== display.trim().toLowerCase()
}

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
    if (!tx.bucket || tx.kind === 'income' || tx.kind === 'refund' || tx.kind === 'transfer') {
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
    if (tx.kind === 'transfer') {
      continue
    }

    const signed = signedAmountCents(tx)
    if (signed > 0) {
      incomeCents += signed
    } else if (signed < 0) {
      expenseCents += Math.abs(signed)
    }
  }

  return {
    incomeCents,
    expenseCents,
    netCents: incomeCents - expenseCents,
  }
}
