/**
 * Transaction money helpers: signed amounts, bucket spend, and cash-flow summary.
 */
import { describe, expect, it } from 'vitest'
import type { Transaction } from '../types/transaction'
import {
  aggregateBucketSpend,
  formatSigned,
  isCredit,
  signedAmountCents,
  summarizeCashFlow,
} from './transactions'

function tx(overrides: Partial<Transaction> & Pick<Transaction, 'kind' | 'amount_cents'>): Transaction {
  return {
    id: overrides.id ?? 1,
    account_id: null,
    account: null,
    merchant: overrides.merchant ?? 'Test Merchant',
    amount_cents: overrides.amount_cents,
    amount: overrides.amount ?? '0.00',
    kind: overrides.kind,
    bucket: overrides.bucket ?? null,
    subcategory: null,
    category: overrides.bucket ?? null,
    transaction_date: overrides.transaction_date ?? '2026-07-20',
    reviewed: overrides.reviewed ?? false,
    review_source: null,
    confidence: null,
    review_explanation: null,
    notes: null,
  }
}

describe('signedAmountCents / isCredit', () => {
  it('treats income and refund as positive credits', () => {
    expect(signedAmountCents(tx({ kind: 'income', amount_cents: 5000 }))).toBe(5000)
    expect(signedAmountCents(tx({ kind: 'refund', amount_cents: -2500 }))).toBe(2500)
    expect(isCredit(tx({ kind: 'income', amount_cents: 100 }))).toBe(true)
  })

  it('treats expenses and transfers as negative outflows', () => {
    expect(signedAmountCents(tx({ kind: 'expense', amount_cents: 1250 }))).toBe(-1250)
    expect(signedAmountCents(tx({ kind: 'expense', amount_cents: -1250 }))).toBe(-1250)
    expect(signedAmountCents(tx({ kind: 'transfer', amount_cents: 4000 }))).toBe(-4000)
    expect(isCredit(tx({ kind: 'expense', amount_cents: 100 }))).toBe(false)
  })
})

describe('formatSigned', () => {
  it('prefixes positive and negative amounts and leaves zero unsigned', () => {
    expect(formatSigned(1250)).toBe('+$12.50')
    expect(formatSigned(-1250)).toBe('-$12.50')
    expect(formatSigned(0)).toBe('$0.00')
  })
})

describe('aggregateBucketSpend', () => {
  it('sums absolute expense spend by bucket and ignores income, refunds, transfers, and uncategorized', () => {
    const result = aggregateBucketSpend([
      tx({ kind: 'expense', bucket: 'need', amount_cents: 5000 }),
      tx({ kind: 'expense', bucket: 'need', amount_cents: 2500 }),
      tx({ kind: 'expense', bucket: 'want', amount_cents: 2500 }),
      tx({ kind: 'expense', bucket: 'savings', amount_cents: 0 }),
      tx({ kind: 'income', bucket: 'savings', amount_cents: 10_000 }),
      tx({ kind: 'refund', bucket: 'want', amount_cents: 500 }),
      tx({ kind: 'transfer', bucket: 'savings', amount_cents: 1000 }),
      tx({ kind: 'expense', bucket: null, amount_cents: 999 }),
    ])

    expect(result).toEqual([
      { bucket: 'need', cents: 7500, percent: 75 },
      { bucket: 'want', cents: 2500, percent: 25 },
      { bucket: 'savings', cents: 0, percent: 0 },
    ])
  })

  it('returns zero percents when there is no spend', () => {
    expect(aggregateBucketSpend([])).toEqual([
      { bucket: 'need', cents: 0, percent: 0 },
      { bucket: 'want', cents: 0, percent: 0 },
      { bucket: 'savings', cents: 0, percent: 0 },
    ])
  })
})

describe('summarizeCashFlow', () => {
  it('aggregates income and expenses while excluding transfers from both sides', () => {
    const summary = summarizeCashFlow([
      tx({ kind: 'income', amount_cents: 10_000 }),
      tx({ kind: 'refund', amount_cents: 500 }),
      tx({ kind: 'expense', amount_cents: 3000 }),
      tx({ kind: 'expense', amount_cents: 2000 }),
      tx({ kind: 'transfer', amount_cents: 4000 }),
    ])

    expect(summary).toEqual({
      incomeCents: 10_500,
      expenseCents: 5000,
      netCents: 5500,
    })
  })
})
