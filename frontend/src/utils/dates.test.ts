/**
 * Date helpers: relative day labels and newest-first date grouping.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { Transaction } from '../types/transaction'
import { formatRelativeDay, groupByDate } from './dates'

function tx(date: string, id: number): Transaction {
  return {
    id,
    account_id: null,
    account: null,
    merchant: `Merchant ${id}`,
    raw_merchant_descriptor: `Merchant ${id}`,
    merchant_id: null,
    amount_cents: 100,
    amount: '1.00',
    kind: 'expense',
    bucket: 'want',
    subcategory: null,
    category_id: null,
    category: 'want',
    transaction_date: date,
    reviewed: false,
    review_source: null,
    confidence: null,
    review_explanation: null,
    notes: null,
  }
}

describe('formatRelativeDay', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-07-25T15:30:00'))
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('labels today and yesterday relative to the current local day', () => {
    expect(formatRelativeDay('2026-07-25')).toBe('Today')
    expect(formatRelativeDay('2026-07-24')).toBe('Yesterday')
  })

  it('formats older dates and returns invalid input unchanged', () => {
    expect(formatRelativeDay('2026-07-20')).toBe('Jul 20, 2026')
    expect(formatRelativeDay('not-a-date')).toBe('not-a-date')
  })
})

describe('groupByDate', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-07-25T12:00:00'))
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('groups transactions by date newest-first while preserving row order within a day', () => {
    const groups = groupByDate([
      tx('2026-07-24', 1),
      tx('2026-07-25', 2),
      tx('2026-07-24', 3),
      tx('2026-07-20', 4),
    ])

    expect(groups.map((group) => group.date)).toEqual([
      '2026-07-25',
      '2026-07-24',
      '2026-07-20',
    ])
    expect(groups[0]?.label).toBe('Today')
    expect(groups[1]?.label).toBe('Yesterday')
    expect(groups[1]?.transactions.map((row) => row.id)).toEqual([1, 3])
  })
})
