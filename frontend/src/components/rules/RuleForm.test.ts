/**
 * RuleForm
 * - submits merchant_id + category_id payload
 * - shows plain-language preview
 * - inline category create preserves draft fields
 */
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createCategory } from '../../api/categoryApi'
import type { Account } from '../../types/account'
import type { Category } from '../../types/category'
import type { Merchant } from '../../types/merchant'
import RuleForm from './RuleForm.vue'

vi.mock('../../api/categoryApi', () => ({
  createCategory: vi.fn(),
}))

const accounts: Account[] = [
  {
    id: 7,
    institution_name: 'Demo Bank',
    name: 'Checking',
    mask: '1234',
    type: 'checking',
    balance_cents: 10000,
    balance: '100.00',
    sync_status: 'healthy',
    logo_key: 'demo',
    sort_order: 1,
  },
]

const merchants: Merchant[] = [
  {
    id: 11,
    name: 'Netflix',
    normalized_name: 'netflix',
    logo_key: null,
    example_descriptors: [
      {
        pattern: 'NETFLIX.COM',
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
    name: 'Streaming',
    normalized_name: 'streaming',
    sort_order: 10,
    is_system: true,
    archived_at: null,
  },
]

beforeEach(() => {
  vi.mocked(createCategory).mockReset()
})

describe('RuleForm', () => {
  it('emits a structured merchant_id and category_id submit payload', async () => {
    const wrapper = mount(RuleForm, {
      props: {
        accounts,
        merchants,
        categories,
        rule: null,
        saving: false,
      },
    })

    await wrapper.get('input').setValue('Netflix streaming')
    await wrapper.get('input[type="search"]').setValue('Netflix')
    await wrapper.get('input[type="search"]').trigger('focus')
    await wrapper.get('[role="option"]').trigger('mousedown')

    await wrapper.get('select').setValue('21')

    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('submit')?.[0]?.[0]).toEqual({
      name: 'Netflix streaming',
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

  it('shows a plain-language preview with merchant example and category', async () => {
    const wrapper = mount(RuleForm, {
      props: {
        accounts,
        merchants,
        categories,
        rule: null,
        saving: false,
      },
    })

    await wrapper.get('input[type="search"]').setValue('Netflix')
    await wrapper.get('input[type="search"]').trigger('focus')
    await wrapper.get('[role="option"]').trigger('mousedown')
    await wrapper.get('select').setValue('21')

    expect(wrapper.get('[role="status"]').text()).toContain('NETFLIX.COM')
    expect(wrapper.get('[role="status"]').text()).toContain('Netflix')
    expect(wrapper.get('[role="status"]').text()).toContain('Streaming')
    expect(wrapper.get('[role="status"]').text()).toContain('Wants')
  })

  it('preserves the rule draft while creating a category inline', async () => {
    vi.mocked(createCategory).mockResolvedValue({
      id: 99,
      user_id: 1,
      bucket: 'want',
      name: 'Date night',
      normalized_name: 'date night',
      sort_order: 1000,
      is_system: false,
      archived_at: null,
    })

    const wrapper = mount(RuleForm, {
      props: {
        accounts,
        merchants,
        categories,
        rule: null,
        saving: false,
      },
    })

    await wrapper.get('input').setValue('Keep my draft')
    await wrapper.get('input[type="search"]').setValue('Netflix')
    await wrapper.get('input[type="search"]').trigger('focus')
    await wrapper.get('[role="option"]').trigger('mousedown')

    const advanced = wrapper.get('details')
    advanced.element.open = true
    await wrapper.vm.$nextTick()

    const priorityInput = wrapper
      .findAll('input')
      .find((input) => input.attributes('type') === 'number')
    expect(priorityInput).toBeDefined()
    await priorityInput!.setValue(3)

    await wrapper
      .findAll('button')
      .find((button) => button.text().includes('New in Wants'))!
      .trigger('click')

    const createNameInput = wrapper
      .findAll('input')
      .find((input) => input.element.closest('.inline-create'))
    expect(createNameInput).toBeDefined()
    await createNameInput!.setValue('Date night')

    await wrapper
      .findAll('button')
      .find((button) => button.text().includes('Create category'))!
      .trigger('click')
    await flushPromises()

    expect(createCategory).toHaveBeenCalledWith({
      name: 'Date night',
      bucket: 'want',
    })
    expect(wrapper.emitted('category-created')?.[0]?.[0]).toMatchObject({
      id: 99,
      name: 'Date night',
    })

    expect((wrapper.get('input').element as HTMLInputElement).value).toBe('Keep my draft')
    expect((priorityInput!.element as HTMLInputElement).value).toBe('3')
    expect(wrapper.get('[role="status"]').text()).toContain('Netflix')
  })
})
