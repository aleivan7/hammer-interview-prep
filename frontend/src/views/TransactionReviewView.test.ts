/**
 * TransactionReviewView
 * - loading / empty / error+retry states
 * - formatted merchant/amount/date rendering
 * - categorize (disable while saving, advance, error stays put)
 * - undo previous review
 * - smart review reload
 */
import {
  enableAutoUnmount,
  flushPromises,
  mount,
  type DOMWrapper,
} from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import {
  fetchReviewQueue,
  fetchTransactionSuggestion,
  undoTransactionReview,
  updateTransaction,
} from '../api/transactionApi'
import { runSmartReview } from '../api/smartReviewApi'
import { CARD_EXIT_MS } from '../composables/useCardSwipe'
import type { Transaction } from '../types/transaction'
import TransactionReviewView from './TransactionReviewView.vue'

enableAutoUnmount(afterEach)

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

const olderMonthTransaction: Transaction = {
  ...firstTransaction,
  id: 3,
  merchant: 'June Books',
  amount_cents: 2400,
  amount: '24.00',
  transaction_date: '2026-06-15',
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

interface ButtonContainer {
  findAll(selector: string): DOMWrapper<Element>[]
}

function buttonContaining(wrapper: ButtonContainer, name: string) {
  const button = wrapper
    .findAll('button')
    .find((candidate) => candidate.text().includes(name))

  if (!button) {
    throw new Error(`Could not find button containing "${name}"`)
  }

  return button
}

async function completeFocusExit(): Promise<void> {
  // Cover the optional pre-exit frame plus the fly-off duration.
  await vi.advanceTimersByTimeAsync(CARD_EXIT_MS + 48)
  await flushPromises()
}

beforeEach(() => {
  vi.useFakeTimers()
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

afterEach(() => {
  vi.useRealTimers()
})

describe('TransactionReviewView', () => {
  it('shows a loading state while transactions are being fetched', () => {
    vi.mocked(fetchReviewQueue).mockReturnValue(new Promise(() => {}))

    const wrapper = mount(TransactionReviewView)

    expect(wrapper.get('[role="status"]').text()).toBe('Loading transactions…')
  })

  it('shows the newest month as a newest-first multi-select queue by default', async () => {
    vi.mocked(fetchReviewQueue).mockResolvedValue([
      olderMonthTransaction,
      firstTransaction,
      secondTransaction,
    ])

    const wrapper = mount(TransactionReviewView)
    await flushPromises()

    expect(wrapper.text()).toContain('July 2026')
    expect(wrapper.text()).toContain('2 transactions awaiting review')
    expect(wrapper.text()).not.toContain('June Books')
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('SwipeMulti-select')

    const focusRows = wrapper.findAll('.focus-transaction')
    expect(focusRows.map((row) => row.text())).toEqual([
      expect.stringContaining('Shell Gas'),
      expect.stringContaining('HEB'),
    ])
  })

  it('navigates between available months and scopes Focus mode to the displayed month', async () => {
    vi.mocked(fetchReviewQueue).mockResolvedValue([
      firstTransaction,
      olderMonthTransaction,
      secondTransaction,
    ])

    const wrapper = mount(TransactionReviewView)
    await flushPromises()

    await buttonContaining(wrapper, 'Previous').trigger('click')

    expect(wrapper.text()).toContain('June 2026')
    expect(wrapper.text()).toContain('June Books')
    expect(wrapper.text()).not.toContain('Shell Gas')

    await buttonContaining(wrapper, 'Start Focus mode').trigger('click')
    await flushPromises()

    const dialog = wrapper.get('[role="dialog"]')
    expect(dialog.text()).toContain('June 2026')
    expect(dialog.text()).toContain('June Books')
    expect(dialog.text()).toContain('Transaction 1 of 1')
  })

  it('opens Focus mode at the newest transaction and advances toward older transactions', async () => {
    vi.mocked(fetchReviewQueue).mockResolvedValue([firstTransaction, secondTransaction])
    vi.mocked(updateTransaction).mockResolvedValue({
      ...secondTransaction,
      bucket: 'want',
      reviewed: true,
    })

    const wrapper = mount(TransactionReviewView)
    await flushPromises()

    await buttonContaining(wrapper, 'Start Focus mode').trigger('click')
    await flushPromises()

    let dialog = wrapper.get('[role="dialog"]')
    expect(dialog.text()).toContain('Shell Gas')
    expect(dialog.text()).toContain('July 21, 2026')
    expect(dialog.text()).toContain('Transaction 1 of 2')
    expect(dialog.text()).toContain('Suggestion')

    await buttonContaining(dialog, 'Wants').trigger('click')
    expect(buttonContaining(dialog, 'Needs').attributes('disabled')).toBeDefined()
    await completeFocusExit()

    expect(updateTransaction).toHaveBeenCalledWith(2, {
      bucket: 'want',
      reviewed: true,
    })
    dialog = wrapper.get('[role="dialog"]')
    expect(dialog.text()).toContain('HEB')
    expect(dialog.text()).toContain('Transaction 2 of 2')
    expect(wrapper.findAll('.focus-transaction')).toHaveLength(1)
  })

  it('disables Focus actions while saving', async () => {
    const update = deferred<Transaction>()
    vi.mocked(fetchReviewQueue).mockResolvedValue([firstTransaction, secondTransaction])
    vi.mocked(updateTransaction).mockReturnValue(update.promise)

    const wrapper = mount(TransactionReviewView)
    await flushPromises()

    await buttonContaining(wrapper, 'Start Focus mode').trigger('click')
    const dialog = wrapper.get('[role="dialog"]')
    await buttonContaining(dialog, 'Wants').trigger('click')
    expect(buttonContaining(dialog, 'Needs').attributes('disabled')).toBeDefined()
    await completeFocusExit()

    expect(buttonContaining(dialog, 'Needs').attributes('disabled')).toBeDefined()
    expect(buttonContaining(dialog, 'Wants').attributes('disabled')).toBeDefined()
    expect(dialog.text()).toContain('Saving category…')
    expect(updateTransaction).toHaveBeenCalledWith(2, {
      bucket: 'want',
      reviewed: true,
    })

    update.resolve({
      ...secondTransaction,
      bucket: 'want',
      category: 'want',
      reviewed: true,
    })
    await flushPromises()

    expect(wrapper.get('[role="dialog"]').text()).toContain('HEB')
  })

  it('keeps the current transaction visible and reports update failures', async () => {
    vi.mocked(fetchReviewQueue).mockResolvedValue([firstTransaction])
    vi.mocked(updateTransaction).mockRejectedValue(new Error('Could not save category'))

    const wrapper = mount(TransactionReviewView)
    await flushPromises()

    await buttonContaining(wrapper, 'Start Focus mode').trigger('click')
    const dialog = wrapper.get('[role="dialog"]')
    await buttonContaining(dialog, 'Needs').trigger('click')
    await completeFocusExit()

    expect(dialog.get('[role="alert"]').text()).toBe('Could not save category')
    expect(dialog.text()).toContain('HEB')
    expect(dialog.text()).toContain('Transaction 1 of 1')
  })

  it('opens Focus mode from a row at that transaction', async () => {
    vi.mocked(fetchReviewQueue).mockResolvedValue([firstTransaction, secondTransaction])

    const wrapper = mount(TransactionReviewView)
    await flushPromises()

    await wrapper.get('[aria-label="Open HEB in focus mode"]').trigger('click')
    await flushPromises()

    const dialog = wrapper.get('[role="dialog"]')
    expect(dialog.text()).toContain('HEB')
    expect(dialog.text()).toContain('Transaction 1 of 1')
  })

  it('skips and undoes transactions within the Focus queue', async () => {
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

    await buttonContaining(wrapper, 'Start Focus mode').trigger('click')
    let dialog = wrapper.get('[role="dialog"]')

    await buttonContaining(dialog, 'Skip').trigger('click')
    expect(dialog.text()).toContain('HEB')

    await buttonContaining(dialog, 'Needs').trigger('click')
    await completeFocusExit()

    dialog = wrapper.get('[role="dialog"]')
    await buttonContaining(dialog, 'Undo').trigger('click')
    await flushPromises()

    expect(undoTransactionReview).toHaveBeenCalledWith(1)
    expect(wrapper.text()).toContain('HEB')
  })

  it('closes Focus mode with Escape and returns to the same month', async () => {
    vi.mocked(fetchReviewQueue).mockResolvedValue([firstTransaction])

    const wrapper = mount(TransactionReviewView)
    await flushPromises()
    await buttonContaining(wrapper, 'Start Focus mode').trigger('click')

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await wrapper.vm.$nextTick()

    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('July 2026')
    expect(wrapper.text()).toContain('HEB')
  })

  it('uses category keyboard shortcuts only while Focus mode is open', async () => {
    vi.mocked(fetchReviewQueue).mockResolvedValue([firstTransaction, secondTransaction])
    vi.mocked(updateTransaction).mockResolvedValue({
      ...secondTransaction,
      bucket: 'need',
      reviewed: true,
    })

    const wrapper = mount(TransactionReviewView)
    await flushPromises()

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight' }))
    expect(updateTransaction).not.toHaveBeenCalled()

    await buttonContaining(wrapper, 'Start Focus mode').trigger('click')
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight' }))
    await completeFocusExit()

    expect(updateTransaction).toHaveBeenCalledWith(2, {
      bucket: 'need',
      reviewed: true,
    })
  })

  it('bulk categorizes selected transactions, preserves failures, and supports undo', async () => {
    vi.mocked(fetchReviewQueue).mockResolvedValue([firstTransaction, secondTransaction])
    vi.mocked(updateTransaction).mockImplementation(async (id) => {
      if (id === secondTransaction.id) {
        throw new Error('Could not update Shell Gas')
      }
      return { ...firstTransaction, bucket: 'need', reviewed: true }
    })
    vi.mocked(undoTransactionReview).mockResolvedValue(firstTransaction)

    const wrapper = mount(TransactionReviewView)
    await flushPromises()

    const checkboxes = wrapper.findAll('input[type="checkbox"]')
    await checkboxes[1].setValue(true)
    await checkboxes[2].setValue(true)
    await buttonContaining(wrapper, 'Needs').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Categorized 1, 1 failed')
    expect(wrapper.text()).toContain('Shell Gas')
    expect(wrapper.text()).not.toContain('HEB')
    expect(wrapper.text()).toContain('1 selected')

    await buttonContaining(wrapper, 'Undo 1').trigger('click')
    await flushPromises()

    expect(undoTransactionReview).toHaveBeenCalledWith(1)
    expect(wrapper.text()).toContain('HEB')
  })

  it('categorizes one transaction from its row', async () => {
    vi.mocked(fetchReviewQueue).mockResolvedValue([firstTransaction])
    vi.mocked(updateTransaction).mockResolvedValue({
      ...firstTransaction,
      bucket: 'savings',
      reviewed: true,
    })

    const wrapper = mount(TransactionReviewView)
    await flushPromises()

    await wrapper.get('select.bucket').setValue('savings')
    await flushPromises()

    expect(updateTransaction).toHaveBeenCalledWith(1, {
      bucket: 'savings',
      reviewed: true,
    })
    expect(wrapper.text()).toContain('All caught up')
  })

  it('keeps an emptied month visible so the user can navigate to another month', async () => {
    vi.mocked(fetchReviewQueue).mockResolvedValue([olderMonthTransaction, firstTransaction])
    vi.mocked(updateTransaction).mockResolvedValue({
      ...firstTransaction,
      bucket: 'savings',
      reviewed: true,
    })

    const wrapper = mount(TransactionReviewView)
    await flushPromises()

    await wrapper.get('select.bucket').setValue('savings')
    await flushPromises()

    expect(wrapper.text()).toContain('July 2026 is complete')
    expect(buttonContaining(wrapper, 'Previous').attributes('disabled')).toBeUndefined()

    await buttonContaining(wrapper, 'Previous').trigger('click')
    expect(wrapper.text()).toContain('June Books')
  })

  it('shows the completion state when no transactions remain', async () => {
    vi.mocked(fetchReviewQueue).mockResolvedValue([])

    const wrapper = mount(TransactionReviewView)
    await flushPromises()

    expect(wrapper.get('[role="status"]').text()).toContain('All caught up')
    expect(wrapper.text()).toContain('There are no unreviewed transactions left')
  })

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
