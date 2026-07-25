import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fetchTransactions, updateTransaction } from '../api/transactionApi'
import type { Transaction } from '../types/transaction'
import TransactionReviewView from './TransactionReviewView.vue'

vi.mock('../api/transactionApi', () => ({
  fetchTransactions: vi.fn(),
  updateTransaction: vi.fn(),
}))

const firstTransaction: Transaction = {
  id: 1,
  merchant: 'HEB',
  amount: '84.23',
  category: null,
  transaction_date: '2026-07-20',
  reviewed: false,
}

const secondTransaction: Transaction = {
  id: 2,
  merchant: 'Shell Gas',
  amount: '42.50',
  category: 'need',
  transaction_date: '2026-07-21',
  reviewed: false,
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

function buttonNamed(wrapper: VueWrapper, name: string) {
  const button = wrapper.findAll('button').find((candidate) => candidate.text() === name)

  if (!button) {
    throw new Error(`Could not find button named "${name}"`)
  }

  return button
}

beforeEach(() => {
  vi.mocked(fetchTransactions).mockReset()
  vi.mocked(updateTransaction).mockReset()
})

describe('TransactionReviewView', () => {
  it('shows a loading state while transactions are being fetched', () => {
    vi.mocked(fetchTransactions).mockReturnValue(new Promise(() => {}))

    const wrapper = mount(TransactionReviewView)

    expect(wrapper.get('[role="status"]').text()).toBe('Loading transactions…')
  })

  it('renders transaction details with formatted currency and date', async () => {
    vi.mocked(fetchTransactions).mockResolvedValue([firstTransaction])

    const wrapper = mount(TransactionReviewView)
    await flushPromises()

    expect(wrapper.text()).toContain('Transaction 1 of 1')
    expect(wrapper.text()).toContain('HEB')
    expect(wrapper.text()).toContain('$84.23')
    expect(wrapper.text()).toContain('July 20, 2026')
    expect(wrapper.text()).toContain('Uncategorized')
    expect(buttonNamed(wrapper, 'Need').exists()).toBe(true)
    expect(buttonNamed(wrapper, 'Want').exists()).toBe(true)
    expect(buttonNamed(wrapper, 'Debt / Savings').exists()).toBe(true)
  })

  it('shows the completion state when no transactions remain', async () => {
    vi.mocked(fetchTransactions).mockResolvedValue([])

    const wrapper = mount(TransactionReviewView)
    await flushPromises()

    expect(wrapper.get('[role="status"]').text()).toContain('All caught up')
    expect(wrapper.text()).toContain('There are no unreviewed transactions left')
  })

  it('shows a load error and retries the request', async () => {
    vi.mocked(fetchTransactions)
      .mockRejectedValueOnce(new Error('Unable to load transactions'))
      .mockResolvedValueOnce([])

    const wrapper = mount(TransactionReviewView)
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Unable to load transactions')

    await buttonNamed(wrapper, 'Try again').trigger('click')
    await flushPromises()

    expect(fetchTransactions).toHaveBeenCalledTimes(2)
    expect(wrapper.text()).toContain('All caught up')
  })

  it('disables category buttons while saving and advances after success', async () => {
    const update = deferred<Transaction>()
    vi.mocked(fetchTransactions).mockResolvedValue([firstTransaction, secondTransaction])
    vi.mocked(updateTransaction).mockReturnValue(update.promise)

    const wrapper = mount(TransactionReviewView)
    await flushPromises()

    await buttonNamed(wrapper, 'Want').trigger('click')

    expect(buttonNamed(wrapper, 'Need').attributes('disabled')).toBeDefined()
    expect(buttonNamed(wrapper, 'Want').attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('Saving category…')
    expect(updateTransaction).toHaveBeenCalledWith(1, {
      category: 'want',
      reviewed: true,
    })

    update.resolve({ ...firstTransaction, category: 'want', reviewed: true })
    await flushPromises()

    expect(wrapper.text()).toContain('Transaction 2 of 2')
    expect(wrapper.text()).toContain('Shell Gas')
    expect(buttonNamed(wrapper, 'Need').attributes('disabled')).toBeUndefined()
  })

  it('keeps the current transaction visible and reports update failures', async () => {
    vi.mocked(fetchTransactions).mockResolvedValue([firstTransaction])
    vi.mocked(updateTransaction).mockRejectedValue(new Error('Could not save category'))

    const wrapper = mount(TransactionReviewView)
    await flushPromises()

    await buttonNamed(wrapper, 'Need').trigger('click')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toBe('Could not save category')
    expect(wrapper.text()).toContain('HEB')
    expect(wrapper.text()).toContain('Transaction 1 of 1')
    expect(buttonNamed(wrapper, 'Need').attributes('disabled')).toBeUndefined()
  })
})
