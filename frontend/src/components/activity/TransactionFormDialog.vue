<script setup lang="ts">
import { computed, nextTick, onUnmounted, reactive, useTemplateRef, watch } from 'vue'
import type { Account } from '../../types/account'
import type { Bucket, TransactionKind } from '../../types/bucket'
import type { Transaction } from '../../types/transaction'
import { dollarsInputToCents } from '../../utils/money'
import AppIcon from '../ui/AppIcon.vue'

const props = defineProps<{
  open: boolean
  accounts: Account[]
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
      subcategory: string | null
      notes: string | null
      reviewed?: boolean
    },
  ]
}>()

const merchantInput = useTemplateRef<HTMLInputElement>('merchantInput')

const form = reactive({
  merchant: '',
  amount: '',
  kind: 'expense' as TransactionKind,
  transaction_date: '',
  account_id: '' as string,
  bucket: '' as '' | Bucket,
  subcategory: '',
  notes: '',
  reviewed: false,
})

const title = computed(() => (props.transaction ? 'Edit transaction' : 'New transaction'))

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

    if (transaction) {
      form.merchant = transaction.merchant
      form.amount = transaction.amount
      form.kind = transaction.kind
      form.transaction_date = transaction.transaction_date
      form.account_id = transaction.account_id ? String(transaction.account_id) : ''
      form.bucket = transaction.bucket ?? ''
      form.subcategory = transaction.subcategory ?? ''
      form.notes = transaction.notes ?? ''
      form.reviewed = transaction.reviewed
    } else {
      form.merchant = ''
      form.amount = ''
      form.kind = 'expense'
      form.transaction_date = new Date().toISOString().slice(0, 10)
      form.account_id = ''
      form.bucket = ''
      form.subcategory = ''
      form.notes = ''
      form.reviewed = false
    }

    await nextTick()
    merchantInput.value?.focus()
  },
)

onUnmounted(() => {
  document.body.style.overflow = ''
  window.removeEventListener('keydown', onKeydown)
})

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
    subcategory: form.subcategory.trim() || null,
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
        Merchant
        <input
          ref="merchantInput"
          v-model="form.merchant"
          class="field"
          required
          maxlength="255"
        />
      </label>

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

      <div class="row">
        <label>
          Bucket
          <select v-model="form.bucket" class="field">
            <option value="">Uncategorized</option>
            <option value="need">Needs</option>
            <option value="want">Wants</option>
            <option value="savings">Savings</option>
          </select>
        </label>
        <label>
          Subcategory
          <input v-model="form.subcategory" class="field" maxlength="100" />
        </label>
      </div>

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
}

header,
footer,
.row {
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

textarea.field {
  min-height: 5rem;
  resize: vertical;
}

footer {
  justify-content: end;
}
</style>
