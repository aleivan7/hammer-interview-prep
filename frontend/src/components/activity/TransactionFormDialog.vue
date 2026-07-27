<script setup lang="ts">
import { computed, nextTick, onUnmounted, reactive, shallowRef, useTemplateRef, watch } from 'vue'
import type { Account } from '../../types/account'
import type { Bucket, TransactionKind } from '../../types/bucket'
import type { Category } from '../../types/category'
import type { Transaction } from '../../types/transaction'
import { dollarsInputToCents } from '../../utils/money'
import CategorySelector from '../rules/CategorySelector.vue'
import AppIcon from '../ui/AppIcon.vue'

const props = defineProps<{
  open: boolean
  accounts: Account[]
  categories: Category[]
  transaction: Transaction | null
  saving: boolean
}>()

const emit = defineEmits<{
  close: []
  submit: [
    payload: {
      merchant: string
      amount_cents: number
      kind: TransactionKind
      transaction_date: string
      account_id: number | null
      bucket: Bucket | null
      category_id: number | null
      subcategory: string | null
      notes: string | null
      reviewed?: boolean
    },
  ]
  'create-category': [payload: { name: string; bucket: Bucket }]
}>()

const merchantInput = useTemplateRef<HTMLInputElement>('merchantInput')
const creatingCategory = shallowRef(false)
const createBucket = shallowRef<Bucket>('want')
const createName = shallowRef('')

const form = reactive({
  merchant: '',
  amount: '',
  kind: 'expense' as TransactionKind,
  transaction_date: '',
  account_id: '' as string,
  bucket: '' as '' | Bucket,
  category_id: null as number | null,
  notes: '',
  reviewed: false,
})

const title = computed(() => (props.transaction ? 'Edit transaction' : 'New transaction'))

const selectedCategory = computed(
  () => props.categories.find((category) => category.id === form.category_id) ?? null,
)

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    emit('close')
  }
}

watch(
  () => props.open,
  (open) => {
    if (!open) {
      document.body.style.overflow = ''
      window.removeEventListener('keydown', onKeydown)
      return
    }

    document.body.style.overflow = 'hidden'
    window.removeEventListener('keydown', onKeydown)
    window.addEventListener('keydown', onKeydown)
  },
)

watch(
  () => [props.open, props.transaction] as const,
  async ([open, transaction]) => {
    if (!open) {
      return
    }

    creatingCategory.value = false
    createName.value = ''

    if (transaction) {
      form.merchant = transaction.raw_merchant_descriptor || transaction.merchant
      form.amount = transaction.amount
      form.kind = transaction.kind
      form.transaction_date = transaction.transaction_date
      form.account_id = transaction.account_id ? String(transaction.account_id) : ''
      form.bucket = transaction.bucket ?? ''
      form.category_id = transaction.category_id
      form.notes = transaction.notes ?? ''
      form.reviewed = transaction.reviewed
    } else {
      form.merchant = ''
      form.amount = ''
      form.kind = 'expense'
      form.transaction_date = new Date().toISOString().slice(0, 10)
      form.account_id = ''
      form.bucket = ''
      form.category_id = null
      form.notes = ''
      form.reviewed = false
    }

    await nextTick()
    merchantInput.value?.focus()
  },
)

watch(
  () => form.category_id,
  (categoryId) => {
    const category = props.categories.find((item) => item.id === categoryId)
    if (category) {
      form.bucket = category.bucket
    }
  },
)

onUnmounted(() => {
  document.body.style.overflow = ''
  window.removeEventListener('keydown', onKeydown)
})

function startCreate(bucket: Bucket): void {
  creatingCategory.value = true
  createBucket.value = bucket
  createName.value = ''
}

function confirmCreate(): void {
  const name = createName.value.trim()
  if (!name) {
    return
  }
  emit('create-category', { name, bucket: createBucket.value })
  creatingCategory.value = false
  createName.value = ''
}

function onSubmit(): void {
  const cents = dollarsInputToCents(form.amount)

  if (cents === null || cents < 0) {
    return
  }

  emit('submit', {
    merchant: form.merchant.trim(),
    amount_cents: cents,
    kind: form.kind,
    transaction_date: form.transaction_date,
    account_id: form.account_id ? Number(form.account_id) : null,
    bucket: form.bucket || null,
    category_id: form.category_id,
    subcategory: selectedCategory.value?.name ?? null,
    notes: form.notes.trim() || null,
    reviewed: form.reviewed,
  })
}
</script>

<template>
  <div
    v-if="open"
    class="backdrop"
    @click.self="emit('close')"
  >
    <form
      class="dialog"
      role="dialog"
      aria-modal="true"
      aria-labelledby="tx-dialog-title"
      @submit.prevent="onSubmit"
    >
      <header>
        <h2 id="tx-dialog-title">{{ title }}</h2>
        <button type="button" class="btn btn-icon" aria-label="Close" @click="emit('close')">
          <AppIcon name="close" :size="16" />
        </button>
      </header>

      <label>
        Merchant descriptor
        <input
          ref="merchantInput"
          v-model="form.merchant"
          class="field"
          required
          maxlength="255"
        />
      </label>
      <p
        v-if="transaction?.canonical_merchant"
        class="canonical-hint"
      >
        Canonical: {{ transaction.canonical_merchant.name }}
      </p>

      <div class="row">
        <label>
          Amount
          <input
            v-model="form.amount"
            class="field"
            required
            inputmode="decimal"
            placeholder="12.50"
          />
        </label>
        <label>
          Date
          <input v-model="form.transaction_date" class="field" required type="date" />
        </label>
      </div>

      <div class="row">
        <label>
          Kind
          <select v-model="form.kind" class="field">
            <option value="expense">Expense</option>
            <option value="income">Income</option>
            <option value="transfer">Transfer</option>
            <option value="refund">Refund</option>
          </select>
        </label>
        <label>
          Account
          <select v-model="form.account_id" class="field">
            <option value="">None</option>
            <option v-for="account in accounts" :key="account.id" :value="String(account.id)">
              {{ account.name }}
            </option>
          </select>
        </label>
      </div>

      <CategorySelector
        v-model="form.category_id"
        :categories="categories"
        :disabled="saving"
        allow-empty
        @create-intent="startCreate"
      />

      <div v-if="creatingCategory" class="inline-create">
        <label>
          New category name
          <input v-model="createName" class="field" maxlength="100" />
        </label>
        <label>
          Bucket
          <select v-model="createBucket" class="field">
            <option value="need">Needs</option>
            <option value="want">Wants</option>
            <option value="savings">Savings</option>
          </select>
        </label>
        <div class="inline-actions">
          <button type="button" class="btn btn-ghost" @click="creatingCategory = false">
            Cancel
          </button>
          <button type="button" class="btn btn-primary" @click="confirmCreate">
            Add category
          </button>
        </div>
      </div>

      <label>
        Bucket
        <select v-model="form.bucket" class="field">
          <option value="">Uncategorized</option>
          <option value="need">Needs</option>
          <option value="want">Wants</option>
          <option value="savings">Savings</option>
        </select>
      </label>
      <p class="hint">
        Pick a detailed category when you have one, or keep a quick Needs/Wants/Savings bucket.
      </p>

      <label>
        Notes
        <textarea v-model="form.notes" class="field" rows="3" maxlength="1000" />
      </label>

      <label class="check">
        <input v-model="form.reviewed" type="checkbox" />
        Mark as reviewed
      </label>

      <footer>
        <button type="button" class="btn btn-ghost" @click="emit('close')">Cancel</button>
        <button type="submit" class="btn btn-primary" :disabled="saving">
          {{ saving ? 'Saving…' : 'Save' }}
        </button>
      </footer>
    </form>
  </div>
</template>

<style scoped>
.backdrop {
  position: fixed;
  inset: 0;
  z-index: 20;
  display: grid;
  place-items: center;
  padding: var(--space-4);
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(6px);
}

.dialog {
  width: min(100%, 34rem);
  display: grid;
  gap: var(--space-3);
  padding: var(--space-5);
  border-radius: var(--radius-lg);
  border: 1px solid var(--border);
  background: var(--bg-elevated);
  box-shadow: var(--shadow-modal);
  max-height: calc(100vh - 2rem);
  overflow: auto;
}

header,
footer,
.row,
.inline-actions {
  display: flex;
  justify-content: space-between;
  gap: var(--space-3);
}

header h2 {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 600;
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

.canonical-hint,
.hint {
  margin: 0;
  color: var(--text-dim);
  font-size: 0.72rem;
}

.inline-create {
  display: grid;
  gap: var(--space-3);
  padding: var(--space-3);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  background: var(--bg-soft);
}

.inline-actions {
  justify-content: end;
}

textarea.field {
  min-height: 5rem;
  resize: vertical;
}

footer {
  justify-content: end;
}
</style>
