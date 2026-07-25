/**
 * TransactionReviewView
 * - loading / empty / error+retry states
 * - formatted merchant/amount/date rendering
 * - categorize (disable while saving, advance, error stays put)
 * - undo previous review
 * - smart review reload
 */
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import {
  fetchReviewQueue,
  fetchTransactionSuggestion,
  undoTransactionReview,
  updateTransaction,
} from '../api/transactionApi'
import { runSmartReview } from '../api/smartReviewApi'
import type { Transaction } from '../types/transaction'
import TransactionReviewView from './TransactionReviewView.vue'

vi.mock('../api/transactionApi', () => ({
  fetchReviewQueue: vi.fn(),
  fetchTransactionSuggestion: vi.fn(),
  updateTransaction: vi.fn(),
  undoTransactionReview: vi.fn(),
}))

vi.mock('../api/smartReviewApi', () => ({
  runSmartReview: vi.fn(),
}))

const firstTransaction: Transaction = {
  id: 1,
  account_id: null,
  merchant: 'HEB',
  amount_cents: 8423,
  amount: '84.23',
  kind: 'expense',
  bucket: null,
  subcategory: null,
  category: null,
  transaction_date: '2026-07-20',
  reviewed: false,
  review_source: null,
  confidence: null,
  review_explanation: null,
  notes: null,
}

const secondTransaction: Transaction = {
  id: 2,
  account_id: null,
  merchant: 'Shell Gas',
  amount_cents: 4250,
  amount: '42.50',
  kind: 'expense',
  bucket: null,
  subcategory: null,
  category: null,
  transaction_date: '2026-07-21',
  reviewed: false,
  review_source: null,
  confidence: null,
  review_explanation: null,
  notes: null,
}

function deferred<T>(): {
  promise: Promise<T>
  resolve: (value: T) => void
  reject: (reason?: unknown) => void
} {
  let resolve!: (value: T) => void
  let reject!: (reason?: unknown) => void

  const promise = new Promise<T>((resolvePromise, rejectPromise) => {
    resolve = resolvePromise
    reject = rejectPromise
  })

  return { promise, resolve, reject }
}

function buttonContaining(wrapper: VueWrapper, name: string) {
  const button = wrapper
    .findAll('button')
    .find((candidate) => candidate.text().includes(name))

  if (!button) {
    throw new Error(`Could not find button containing "${name}"`)
  }

  return button
}

beforeEach(() => {
  vi.mocked(fetchReviewQueue).mockReset()
  vi.mocked(fetchTransactionSuggestion).mockReset()
  vi.mocked(updateTransaction).mockReset()
  vi.mocked(undoTransactionReview).mockReset()
  vi.mocked(runSmartReview).mockReset()
  vi.mocked(fetchTransactionSuggestion).mockResolvedValue({
    bucket: 'need',
    subcategory: 'groceries',
    confidence: 86,
    source: 'heuristic',
    explanation: 'Heuristic match for merchant containing "heb".',
    auto_review: true,
  })
})

describe('TransactionReviewView', () => {
  /** Shows a status message while the review queue request is in flight. */
  it('shows a loading state while transactions are being fetched', () => {
    vi.mocked(fetchReviewQueue).mockReturnValue(new Promise(() => {}))

    const wrapper = mount(TransactionReviewView)

    expect(wrapper.get('[role="status"]').text()).toBe('Loading transactions…')
  })

  /** Renders merchant, formatted dollars, date, suggestion, and category actions. */
  it('renders transaction details with formatted currency and date', async () => {
    vi.mocked(fetchReviewQueue).mockResolvedValue([firstTransaction])

    const wrapper = mount(TransactionReviewView)
    await flushPromises()

    expect(wrapper.text()).toContain('Transaction 1 of 1')
    expect(wrapper.text()).toContain('HEB')
    expect(wrapper.text()).toContain('$84.23')
    expect(wrapper.text()).toContain('July 20, 2026')
    expect(wrapper.text()).toContain('Suggestion')
    expect(buttonContaining(wrapper, 'Need').exists()).toBe(true)
    expect(buttonContaining(wrapper, 'Want').exists()).toBe(true)
    expect(buttonContaining(wrapper, 'Savings').exists()).toBe(true)
  })

  /** Empty queue shows the “all caught up” completion state. */
  it('shows the completion state when no transactions remain', async () => {
    vi.mocked(fetchReviewQueue).mockResolvedValue([])

    const wrapper = mount(TransactionReviewView)
    await flushPromises()

    expect(wrapper.get('[role="status"]').text()).toContain('All caught up')
    expect(wrapper.text()).toContain('There are no unreviewed transactions left')
  })

  /** Load failure surfaces an alert; Try again refetches the queue. */
  it('shows a load error and retries the request', async () => {
    vi.mocked(fetchReviewQueue)
      .mockRejectedValueOnce(new Error('Unable to load transactions'))
      .mockResolvedValueOnce([])

    const wrapper = mount(TransactionReviewView)
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Unable to load transactions')

    await buttonContaining(wrapper, 'Try again').trigger('click')
    await flushPromises()

    expect(fetchReviewQueue).toHaveBeenCalledTimes(2)
    expect(wrapper.text()).toContain('All caught up')
  })

  /** While saving, buttons disable; on success the queue advances to the next item. */
  it('disables category buttons while saving and advances after success', async () => {
    const update = deferred<Transaction>()
    vi.mocked(fetchReviewQueue).mockResolvedValue([firstTransaction, secondTransaction])
    vi.mocked(updateTransaction).mockReturnValue(update.promise)

    const wrapper = mount(TransactionReviewView)
    await flushPromises()

    await buttonContaining(wrapper, 'Want').trigger('click')

    expect(buttonContaining(wrapper, 'Need').attributes('disabled')).toBeDefined()
    expect(buttonContaining(wrapper, 'Want').attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('Saving category…')
    expect(updateTransaction).toHaveBeenCalledWith(1, {
      bucket: 'want',
      reviewed: true,
    })

    update.resolve({
      ...firstTransaction,
      bucket: 'want',
      category: 'want',
      reviewed: true,
    })
    await flushPromises()

    expect(wrapper.text()).toContain('Transaction 2 of 2')
    expect(wrapper.text()).toContain('Shell Gas')
    expect(buttonContaining(wrapper, 'Need').attributes('disabled')).toBeUndefined()
  })

  /** Failed categorize keeps the same transaction visible and shows an error. */
  it('keeps the current transaction visible and reports update failures', async () => {
    vi.mocked(fetchReviewQueue).mockResolvedValue([firstTransaction])
    vi.mocked(updateTransaction).mockRejectedValue(new Error('Could not save category'))

    const wrapper = mount(TransactionReviewView)
    await flushPromises()

    await buttonContaining(wrapper, 'Need').trigger('click')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toBe('Could not save category')
    expect(wrapper.text()).toContain('HEB')
    expect(wrapper.text()).toContain('Transaction 1 of 1')
    expect(buttonContaining(wrapper, 'Need').attributes('disabled')).toBeUndefined()
  })

  /** Undo calls the undo API and restores the previously reviewed transaction. */
  it('undoes the previous review', async () => {
    vi.mocked(fetchReviewQueue).mockResolvedValue([firstTransaction, secondTransaction])
    vi.mocked(updateTransaction).mockResolvedValue({
      ...firstTransaction,
      bucket: 'need',
      category: 'need',
      reviewed: true,
    })
    vi.mocked(undoTransactionReview).mockResolvedValue({
      ...firstTransaction,
      reviewed: false,
    })

    const wrapper = mount(TransactionReviewView)
    await flushPromises()

    await buttonContaining(wrapper, 'Need').trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Shell Gas')

    await buttonContaining(wrapper, 'Undo').trigger('click')
    await flushPromises()

    expect(undoTransactionReview).toHaveBeenCalledWith(1)
    expect(wrapper.text()).toContain('HEB')
  })

  /** Smart Review runs the batch endpoint and reloads the remaining queue. */
  it('runs smart review and reloads the queue', async () => {
    vi.mocked(fetchReviewQueue)
      .mockResolvedValueOnce([firstTransaction])
      .mockResolvedValueOnce([])
    vi.mocked(runSmartReview).mockResolvedValue({
      applied: [
        {
          id: 1,
          merchant: 'HEB',
          bucket: 'need',
          subcategory: 'groceries',
          confidence: 86,
          explanation: 'Heuristic',
          source: 'heuristic',
        },
      ],
      skipped: [],
      applied_count: 1,
      skipped_count: 0,
      batch_key: 'abc',
    })

    const wrapper = mount(TransactionReviewView)
    await flushPromises()

    await buttonContaining(wrapper, 'Smart Review').trigger('click')
    await flushPromises()

    expect(runSmartReview).toHaveBeenCalled()
    expect(wrapper.text()).toContain('Applied 1, skipped 0')
    expect(wrapper.text()).toContain('All caught up')
  })
})
