/**
 * RulesView
 * - loads rules, accounts, merchants, and categories
 * - create flow posts merchant_id + category_id
 */
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fetchAccounts } from '../api/accountApi'
import { fetchCategories } from '../api/categoryApi'
import { fetchMerchants } from '../api/merchantApi'
import { createRule, deleteRule, fetchRules, updateRule } from '../api/rulesApi'
import type { Category } from '../types/category'
import type { Merchant } from '../types/merchant'
import type { CategorizationRule } from '../types/rule'
import RulesView from './RulesView.vue'

vi.mock('../api/accountApi', () => ({
  fetchAccounts: vi.fn(),
}))

vi.mock('../api/categoryApi', () => ({
  fetchCategories: vi.fn(),
  createCategory: vi.fn(),
}))

vi.mock('../api/merchantApi', () => ({
  fetchMerchants: vi.fn(),
}))

vi.mock('../api/rulesApi', () => ({
  fetchRules: vi.fn(),
  createRule: vi.fn(),
  updateRule: vi.fn(),
  deleteRule: vi.fn(),
}))

const merchants: Merchant[] = [
  {
    id: 11,
    name: 'Spotify',
    normalized_name: 'spotify',
    logo_key: null,
    example_descriptors: [
      {
        pattern: 'SPOTIFY USA',
        match_strategy: 'prefix',
        priority: 10,
        enabled: true,
      },
    ],
  },
]

const categories: Category[] = [
  {
    id: 21,
    user_id: null,
    bucket: 'want',
    name: 'Music',
    normalized_name: 'music',
    sort_order: 10,
    is_system: true,
    archived_at: null,
  },
]

const existingRule: CategorizationRule = {
  id: 1,
  name: 'Spotify music',
  merchant_id: 11,
  merchant_contains: 'SPOTIFY',
  canonical_merchant: {
    id: 11,
    name: 'Spotify',
    normalized_name: 'spotify',
    logo_key: null,
  },
  account_id: null,
  amount_cents_min: null,
  amount_cents_max: null,
  category_id: 21,
  target_category: {
    id: 21,
    name: 'Music',
    bucket: 'want',
    is_system: true,
    archived_at: null,
  },
  target_bucket: 'want',
  target_subcategory: 'Music',
  priority: 10,
  enabled: true,
  auto_review: true,
}

beforeEach(() => {
  vi.mocked(fetchAccounts).mockResolvedValue([])
  vi.mocked(fetchMerchants).mockResolvedValue(merchants)
  vi.mocked(fetchCategories).mockResolvedValue(categories)
  vi.mocked(fetchRules).mockResolvedValue([existingRule])
  vi.mocked(createRule).mockResolvedValue(existingRule)
  vi.mocked(updateRule).mockResolvedValue(existingRule)
  vi.mocked(deleteRule).mockResolvedValue(undefined)
})

describe('RulesView', () => {
  it('loads catalog data and lists structured rules', async () => {
    const wrapper = mount(RulesView)
    await flushPromises()

    expect(fetchAccounts).toHaveBeenCalled()
    expect(fetchMerchants).toHaveBeenCalled()
    expect(fetchCategories).toHaveBeenCalled()
    expect(fetchRules).toHaveBeenCalled()
    expect(wrapper.text()).toContain('Spotify music')
    expect(wrapper.text()).toContain('Spotify → Music')
  })

  it('creates a rule with merchant_id and category_id', async () => {
    const wrapper = mount(RulesView)
    await flushPromises()

    await wrapper
      .findAll('button')
      .find((button) => button.text().includes('New rule'))!
      .trigger('click')
    await flushPromises()

    await wrapper.get('input').setValue('Spotify rule')
    await wrapper.get('input[type="search"]').setValue('Spotify')
    await wrapper.get('input[type="search"]').trigger('focus')
    await wrapper.get('[role="option"]').trigger('mousedown')
    await wrapper.get('select').setValue('21')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(createRule).toHaveBeenCalledWith({
      name: 'Spotify rule',
      merchant_id: 11,
      category_id: 21,
      account_id: null,
      amount_cents_min: null,
      amount_cents_max: null,
      priority: 10,
      enabled: true,
      auto_review: true,
    })
  })
})
