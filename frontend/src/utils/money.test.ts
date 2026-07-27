/**
 * Money helpers: dollar/cent formatting and dollarsInputToCents parsing.
 */
import { describe, expect, it } from 'vitest'
import {
  dollarsInputToCents,
  formatCents,
  formatDate,
  formatDollars,
  formatShortDate,
} from './money'

describe('formatDollars / formatCents', () => {
  it('formats cent integers as USD currency', () => {
    expect(formatCents(8423)).toBe('$84.23')
    expect(formatDollars(1250)).toBe('$12.50')
  })

  it('formats decimal dollar strings without dividing by 100', () => {
    expect(formatDollars('84.23')).toBe('$84.23')
  })

  it('returns the original value when the amount is not numeric', () => {
    expect(formatDollars('not-a-number')).toBe('not-a-number')
  })
})

describe('dollarsInputToCents', () => {
  it('parses whole dollars and up to two fractional digits', () => {
    expect(dollarsInputToCents('0')).toBe(0)
    expect(dollarsInputToCents('12')).toBe(1200)
    expect(dollarsInputToCents('12.5')).toBe(1250)
    expect(dollarsInputToCents('12.50')).toBe(1250)
    expect(dollarsInputToCents('-12.50')).toBe(-1250)
  })

  it('rejects blank, malformed, and over-precise inputs', () => {
    expect(dollarsInputToCents('')).toBeNull()
    expect(dollarsInputToCents('  ')).toBeNull()
    expect(dollarsInputToCents('12.345')).toBeNull()
    expect(dollarsInputToCents('12.5.0')).toBeNull()
    expect(dollarsInputToCents('$12.50')).toBeNull()
    expect(dollarsInputToCents('1,250.00')).toBeNull()
    expect(dollarsInputToCents('abc')).toBeNull()
  })
})

describe('formatDate / formatShortDate', () => {
  it('formats valid ISO dates and passes invalid values through', () => {
    expect(formatDate('2026-07-20')).toBe('July 20, 2026')
    expect(formatShortDate('2026-07-20')).toBe('Jul 20')
    expect(formatDate('not-a-date')).toBe('not-a-date')
    expect(formatShortDate('not-a-date')).toBe('not-a-date')
  })
})
