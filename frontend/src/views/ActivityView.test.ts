/**
 * ActivityView
 * - edit form sends bucket: null when clearing a category (not omitted)
 */
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fetchAccounts } from '../api/accountApi'
import {
  createTransaction,
  fetchTransactions,
  updateTransaction,
} from '../api/transactionApi'
import type { Transaction } from '../types/transaction'
import ActivityView from './ActivityView.vue'

vi.mock('../api/accountApi', () => ({
  fetchAccounts: vi.fn(),
}))

vi.mock('../api/transactionApi', () => ({
  createTransaction: vi.fn(),
  fetchTransactions: vi.fn(),
  updateTransaction: vi.fn(),
}))

const transaction: Transaction = {
  id: 1,
  account_id: null,
  account: null,
  merchant: 'Corner Market',
  amount_cents: 1250,
  amount: '12.50',
  kind: 'expense',
  bucket: 'want',
  subcategory: null,
  category: 'want',
  transaction_date: '2026-07-22',
  reviewed: false,
  review_source: null,
  confidence: null,
  review_explanation: null,
  notes: null,
}

beforeEach(() => {
  vi.mocked(fetchAccounts).mockResolvedValue([])
  vi.mocked(fetchTransactions).mockResolvedValue([transaction])
  vi.mocked(updateTransaction).mockResolvedValue({
    ...transaction,
    bucket: null,
    category: null,
  })
  vi.mocked(createTransaction).mockResolvedValue(transaction)
})

describe('ActivityView', () => {
  /** Clearing the bucket select must POST/PATCH with bucket: null so validation can run. */
  it('sends an explicit null when clearing a transaction bucket', async () => {
    const wrapper = mount(ActivityView)
    await flushPromises()

    const editButton = wrapper
      .findAll('button')
      .find((button) => button.text() === 'Edit')
    expect(editButton).toBeDefined()

    await editButton!.trigger('click')
    await flushPromises()

    const bucketSelect = wrapper
      .findAll('select')
      .find((select) => select.text().includes('Uncategorized'))
    expect(bucketSelect).toBeDefined()

    await bucketSelect!.setValue('')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(updateTransaction).toHaveBeenCalledWith(
      transaction.id,
      expect.objectContaining({
        bucket: null,
        reviewed: false,
      }),
    )
  })
})
