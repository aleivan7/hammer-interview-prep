<script setup lang="ts">
import { computed, reactive, shallowRef, watch } from 'vue'
import { createCategory } from '../../api/categoryApi'
import { BUCKET_LABELS, type Bucket } from '../../types/bucket'
import type { Account } from '../../types/account'
import type { Category } from '../../types/category'
import type { Merchant } from '../../types/merchant'
import type { CategorizationRule, StoreCategorizationRulePayload } from '../../types/rule'
import { dollarsInputToCents } from '../../utils/money'
import CategorySelector from './CategorySelector.vue'
import MerchantSelector from './MerchantSelector.vue'

const props = defineProps<{
  accounts: Account[]
  merchants: Merchant[]
  categories: Category[]
  rule: CategorizationRule | null
  saving: boolean
}>()

const emit = defineEmits<{
  cancel: []
  submit: [payload: StoreCategorizationRulePayload]
  'category-created': [category: Category]
}>()

const form = reactive({
  name: '',
  merchant_id: null as number | null,
  category_id: null as number | null,
  account_id: '',
  amount_min: '',
  amount_max: '',
  priority: 10,
  enabled: true,
  auto_review: true,
})

const advancedOpen = shallowRef(false)
const creatingCategory = shallowRef(false)
const createBucket = shallowRef<Bucket>('want')
const createName = shallowRef('')
const createError = shallowRef<string | null>(null)
const createSaving = shallowRef(false)
const formError = shallowRef<string | null>(null)

const selectedMerchant = computed(
  () => props.merchants.find((merchant) => merchant.id === form.merchant_id) ?? null,
)

const selectedCategory = computed(
  () => props.categories.find((category) => category.id === form.category_id) ?? null,
)

const preview = computed(() => {
  const merchantName = selectedMerchant.value?.name
  const example =
    selectedMerchant.value?.example_descriptors.find((item) => item.enabled)?.pattern ??
    selectedMerchant.value?.example_descriptors[0]?.pattern ??
    null
  const category = selectedCategory.value

  if (!merchantName && !category) {
    return 'Choose a merchant and category to preview this rule.'
  }

  if (!merchantName) {
    return `When a matching merchant is selected, categorize as ${category!.name} (${BUCKET_LABELS[category!.bucket]}).`
  }

  if (!category) {
    return example
      ? `When a charge looks like “${example}” for ${merchantName}, choose a target category.`
      : `When a charge resolves to ${merchantName}, choose a target category.`
  }

  return example
    ? `When a charge looks like “${example}” for ${merchantName}, categorize as ${category.name} (${BUCKET_LABELS[category.bucket]}).`
    : `When a charge resolves to ${merchantName}, categorize as ${category.name} (${BUCKET_LABELS[category.bucket]}).`
})

watch(
  () => props.rule,
  (rule) => {
    creatingCategory.value = false
    createError.value = null
    formError.value = null

    if (rule) {
      form.name = rule.name
      form.merchant_id = rule.merchant_id
      form.category_id = rule.category_id
      form.account_id = rule.account_id ? String(rule.account_id) : ''
      form.amount_min =
        rule.amount_cents_min == null ? '' : (rule.amount_cents_min / 100).toFixed(2)
      form.amount_max =
        rule.amount_cents_max == null ? '' : (rule.amount_cents_max / 100).toFixed(2)
      form.priority = rule.priority
      form.enabled = rule.enabled
      form.auto_review = rule.auto_review
      advancedOpen.value = Boolean(
        rule.account_id ||
          rule.amount_cents_min != null ||
          rule.amount_cents_max != null ||
          rule.priority !== 10 ||
          !rule.enabled ||
          !rule.auto_review,
      )
    } else {
      form.name = ''
      form.merchant_id = null
      form.category_id = null
      form.account_id = ''
      form.amount_min = ''
      form.amount_max = ''
      form.priority = 10
      form.enabled = true
      form.auto_review = true
      advancedOpen.value = false
    }
  },
  { immediate: true },
)

function parseOptionalCents(value: string): number | null {
  if (!value.trim()) {
    return null
  }

  return dollarsInputToCents(value)
}

function startCreate(bucket: Bucket): void {
  creatingCategory.value = true
  createBucket.value = bucket
  createName.value = ''
  createError.value = null
}

async function submitCreateCategory(): Promise<void> {
  const name = createName.value.trim()
  if (!name || createSaving.value) {
    return
  }

  createSaving.value = true
  createError.value = null

  try {
    const category = await createCategory({
      name,
      bucket: createBucket.value,
    })
    emit('category-created', category)
    form.category_id = category.id
    creatingCategory.value = false
    createName.value = ''
  } catch (err) {
    createError.value = err instanceof Error ? err.message : 'Failed to create category.'
  } finally {
    createSaving.value = false
  }
}

function cancelCreate(): void {
  creatingCategory.value = false
  createError.value = null
  createName.value = ''
}

function onSubmit(): void {
  formError.value = null

  if (form.merchant_id == null || form.category_id == null) {
    formError.value = 'Select both a merchant and a category.'
    return
  }

  const name = form.name.trim()
  if (!name) {
    formError.value = 'Name is required.'
    return
  }

  emit('submit', {
    name,
    merchant_id: form.merchant_id,
    category_id: form.category_id,
    account_id: form.account_id ? Number(form.account_id) : null,
    amount_cents_min: parseOptionalCents(form.amount_min),
    amount_cents_max: parseOptionalCents(form.amount_max),
    priority: Number(form.priority),
    enabled: form.enabled,
    auto_review: form.auto_review,
  })
}
</script>

<template>
  <form class="form panel" @submit.prevent="onSubmit">
    <header class="panel-header">
      <h2>{{ rule ? 'Edit rule' : 'New rule' }}</h2>
      <button type="button" class="btn btn-ghost" @click="emit('cancel')">Close</button>
    </header>

    <label>
      Name
      <input v-model="form.name" class="field" required maxlength="120" />
    </label>

    <MerchantSelector
      v-model="form.merchant_id"
      :merchants="merchants"
      :disabled="saving"
    />

    <CategorySelector
      v-model="form.category_id"
      :categories="categories"
      :disabled="saving || createSaving"
      @create-intent="startCreate"
    />

    <div v-if="creatingCategory" class="inline-create panel soft">
      <p class="inline-title">
        Create custom {{ BUCKET_LABELS[createBucket] }} category
      </p>
      <label>
        Category name
        <input
          v-model="createName"
          class="field"
          maxlength="100"
          required
          :disabled="createSaving"
        />
      </label>
      <label>
        Bucket
        <select v-model="createBucket" class="field" :disabled="createSaving">
          <option value="need">Needs</option>
          <option value="want">Wants</option>
          <option value="savings">Savings</option>
        </select>
      </label>
      <p v-if="createError" class="error" role="alert">{{ createError }}</p>
      <div class="inline-actions">
        <button type="button" class="btn btn-ghost" :disabled="createSaving" @click="cancelCreate">
          Cancel
        </button>
        <button
          type="button"
          class="btn btn-primary"
          :disabled="createSaving || !createName.trim()"
          @click="submitCreateCategory"
        >
          {{ createSaving ? 'Creating…' : 'Create category' }}
        </button>
      </div>
    </div>

    <p class="preview" role="status">{{ preview }}</p>
    <p v-if="formError" class="error" role="alert">{{ formError }}</p>

    <details class="advanced" :open="advancedOpen" @toggle="advancedOpen = ($event.target as HTMLDetailsElement).open">
      <summary>Advanced conditions</summary>

      <div class="advanced-body">
        <label>
          Account (optional)
          <select v-model="form.account_id" class="field">
            <option value="">Any account</option>
            <option v-for="account in accounts" :key="account.id" :value="String(account.id)">
              {{ account.name }}
            </option>
          </select>
        </label>

        <div class="row">
          <label>
            Min amount
            <input
              v-model="form.amount_min"
              class="field"
              inputmode="decimal"
              placeholder="optional"
            />
          </label>
          <label>
            Max amount
            <input
              v-model="form.amount_max"
              class="field"
              inputmode="decimal"
              placeholder="optional"
            />
          </label>
        </div>

        <label>
          Priority
          <input
            v-model.number="form.priority"
            class="field"
            type="number"
            min="1"
            max="1000"
            required
          />
        </label>

        <label class="check">
          <input v-model="form.enabled" type="checkbox" />
          Enabled
        </label>
        <label class="check">
          <input v-model="form.auto_review" type="checkbox" />
          Auto-review on Smart Review
        </label>
      </div>
    </details>

    <footer>
      <button type="button" class="btn btn-ghost" @click="emit('cancel')">Cancel</button>
      <button type="submit" class="btn btn-primary" :disabled="saving || createSaving">
        {{ saving ? 'Saving…' : 'Save rule' }}
      </button>
    </footer>
  </form>
</template>

<style scoped>
.form {
  gap: var(--space-4);
}

header,
footer,
.row,
.inline-actions {
  display: flex;
  justify-content: space-between;
  gap: var(--space-3);
}

.row > label {
  flex: 1;
}

label {
  display: grid;
  gap: 0.3rem;
  color: var(--text-muted);
  font-size: 0.8125rem;
}

.check {
  grid-template-columns: auto 1fr;
  align-items: center;
  color: var(--text);
}

.preview {
  margin: 0;
  padding: var(--space-3);
  border-radius: var(--radius-sm);
  border: 1px solid var(--border);
  background: rgba(255, 255, 255, 0.03);
  color: var(--text-muted);
  font-size: 0.8125rem;
}

.soft {
  gap: var(--space-3);
  padding: var(--space-4);
  background: var(--bg-soft);
}

.inline-title {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--text);
}

.inline-actions {
  justify-content: end;
}

.advanced {
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: var(--space-3) var(--space-4);
}

.advanced summary {
  cursor: pointer;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--text-muted);
}

.advanced-body {
  display: grid;
  gap: var(--space-3);
  margin-top: var(--space-3);
}

.error {
  margin: 0;
  color: var(--danger);
  font-size: 0.8125rem;
}

footer {
  justify-content: end;
}
</style>
