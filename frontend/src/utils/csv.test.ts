/**
 * CSV export: spreadsheet formula injection neutralization and cell escaping.
 */
import { describe, expect, it } from 'vitest'
import type { Transaction } from '../types/transaction'
import { transactionsToCsv } from './csv'

function tx(overrides: Partial<Transaction> = {}): Transaction {
  return {
    id: 1,
    account_id: null,
    account: overrides.account ?? null,
    merchant: overrides.merchant ?? 'Corner Market',
    raw_merchant_descriptor: overrides.raw_merchant_descriptor ?? overrides.merchant ?? 'Corner Market',
    merchant_id: overrides.merchant_id ?? null,
    amount_cents: 1250,
    amount: overrides.amount ?? '12.50',
    kind: overrides.kind ?? 'expense',
    bucket: overrides.bucket ?? 'want',
    subcategory: null,
    category_id: overrides.category_id ?? null,
    category: overrides.bucket ?? 'want',
    transaction_date: overrides.transaction_date ?? '2026-07-22',
    reviewed: overrides.reviewed ?? true,
    review_source: null,
    confidence: null,
    review_explanation: null,
    notes: null,
  }
}

describe('transactionsToCsv', () => {
  it('emits a header row and mapped transaction fields', () => {
    const csv = transactionsToCsv([
      tx({
        account: {
          id: 9,
          name: 'Everyday Checking',
          institution_name: 'Local Bank',
        },
      }),
    ])

    expect(csv).toBe(
      [
        'Date,Merchant,Kind,Bucket,Amount,Reviewed,Account',
        '2026-07-22,Corner Market,expense,Wants,12.50,yes,Everyday Checking',
      ].join('\n'),
    )
  })

  it('neutralizes spreadsheet formula injection and escapes quotes/commas/newlines', () => {
    const csv = transactionsToCsv([
      tx({
        merchant: '=cmd|"/C calc"!A0',
        amount: '+12.50',
        bucket: null,
        reviewed: false,
        account: {
          id: 1,
          name: 'Acme, "Main"',
          institution_name: 'Bank',
        },
      }),
      tx({
        merchant: '-SUM(A1:A2)',
        amount: '@danger',
        notes: null,
      }),
      tx({
        merchant: 'Line\nBreak',
      }),
    ])

    const lines = csv.split('\n')
    expect(lines[1]).toContain("'=cmd|\"\"/C calc\"\"!A0")
    expect(lines[1]).toContain("'+12.50")
    expect(lines[1]).toContain('"Acme, ""Main"""')
    expect(lines[1]).toContain(',no,')
    expect(lines[2]).toContain("'-SUM(A1:A2)")
    expect(lines[2]).toContain("'@danger")
    expect(csv).toContain('"Line\nBreak"')
  })
})
