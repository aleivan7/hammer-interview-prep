/**
 * ActivityView
 * - edit form sends bucket: null when clearing a category (not omitted)
 */
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fetchAccounts } from '../api/accountApi'
import { fetchCategories } from '../api/categoryApi'
import {
  createTransaction,
  fetchTransactions,
  updateTransaction,
} from '../api/transactionApi'
import type { Category } from '../types/category'
import type { Transaction } from '../types/transaction'
import ActivityView from './ActivityView.vue'

vi.mock('../api/accountApi', () => ({
  fetchAccounts: vi.fn(),
}))

vi.mock('../api/categoryApi', () => ({
  fetchCategories: vi.fn(),
  createCategory: vi.fn(),
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
  raw_merchant_descriptor: 'Corner Market',
  merchant_id: null,
  amount_cents: 1250,
  amount: '12.50',
  kind: 'expense',
  bucket: 'want',
  subcategory: null,
  category_id: null,
  category: 'want',
  transaction_date: '2026-07-22',
  reviewed: false,
  review_source: null,
  confidence: null,
  review_explanation: null,
  notes: null,
}

const dining: Category = {
  id: 21,
  user_id: null,
  bucket: 'want',
  name: 'Dining',
  normalized_name: 'dining',
  sort_order: 10,
  is_system: true,
  archived_at: null,
}

beforeEach(() => {
  vi.clearAllMocks()
  vi.mocked(fetchAccounts).mockResolvedValue([])
  vi.mocked(fetchCategories).mockResolvedValue([])
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
      .find((button) => button.text().includes('Edit'))
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

  it('clears an incompatible category when the bucket changes', async () => {
    vi.mocked(fetchCategories).mockResolvedValue([dining])
    vi.mocked(fetchTransactions).mockResolvedValue([
      {
        ...transaction,
        bucket: 'want',
        subcategory: 'Dining',
        category_id: dining.id,
        detailed_category: {
          id: dining.id,
          name: dining.name,
          bucket: dining.bucket,
          is_system: dining.is_system,
          archived_at: null,
        },
      },
    ])
    const wrapper = mount(ActivityView)
    await flushPromises()

    await wrapper.findAll('button').find((button) => button.text().includes('Edit'))!.trigger('click')
    await flushPromises()
    await wrapper.get('select[aria-label="Bucket"]').setValue('need')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(updateTransaction).toHaveBeenCalledWith(
      transaction.id,
      expect.objectContaining({
        bucket: 'need',
        category_id: null,
        subcategory: null,
      }),
    )
  })

  it('omits an unchanged archived category during unrelated edits', async () => {
    vi.mocked(fetchCategories).mockResolvedValue([])
    vi.mocked(fetchTransactions).mockResolvedValue([
      {
        ...transaction,
        bucket: 'want',
        subcategory: 'Old Dining',
        category_id: 99,
        detailed_category: {
          id: 99,
          name: 'Old Dining',
          bucket: 'want',
          is_system: false,
          archived_at: '2026-07-01T00:00:00Z',
        },
      },
    ])
    const wrapper = mount(ActivityView)
    await flushPromises()

    await wrapper.findAll('button').find((button) => button.text().includes('Edit'))!.trigger('click')
    await flushPromises()
    await wrapper.get('textarea').setValue('Updated note')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    const payload = vi.mocked(updateTransaction).mock.calls[0]?.[1]
    expect(payload).toBeDefined()
    expect(payload).not.toHaveProperty('category_id')
    expect(payload).not.toHaveProperty('subcategory')
    expect(payload).toMatchObject({ notes: 'Updated note' })
  })
})
