<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import type { Account } from '../../types/account'
import type { Bucket, TransactionKind } from '../../types/bucket'
import type { Transaction } from '../../types/transaction'
import { dollarsInputToCents } from '../../utils/money'

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

watch(
  () => [props.open, props.transaction] as const,
  ([open, transaction]) => {
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
  },
)

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
  <div v-if="open" class="backdrop" @click.self="emit('close')">
    <form class="dialog" @submit.prevent="onSubmit">
      <header>
        <h2>{{ title }}</h2>
        <button type="button" class="ghost" @click="emit('close')">Close</button>
      </header>

      <label>
        Merchant
        <input v-model="form.merchant" required maxlength="255" />
      </label>

      <div class="row">
        <label>
          Amount
          <input v-model="form.amount" required inputmode="decimal" placeholder="12.50" />
        </label>
        <label>
          Date
          <input v-model="form.transaction_date" required type="date" />
        </label>
      </div>

      <div class="row">
        <label>
          Kind
          <select v-model="form.kind">
            <option value="expense">Expense</option>
            <option value="income">Income</option>
            <option value="transfer">Transfer</option>
            <option value="refund">Refund</option>
          </select>
        </label>
        <label>
          Account
          <select v-model="form.account_id">
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
          <select v-model="form.bucket">
            <option value="">Uncategorized</option>
            <option value="need">Needs</option>
            <option value="want">Wants</option>
            <option value="savings">Savings</option>
          </select>
        </label>
        <label>
          Subcategory
          <input v-model="form.subcategory" maxlength="100" />
        </label>
      </div>

      <label>
        Notes
        <textarea v-model="form.notes" rows="3" maxlength="1000" />
      </label>

      <label class="check">
        <input v-model="form.reviewed" type="checkbox" />
        Mark as reviewed
      </label>

      <footer>
        <button type="button" class="ghost" @click="emit('close')">Cancel</button>
        <button type="submit" :disabled="saving">
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
  padding: 1rem;
  background: rgba(4, 8, 16, 0.72);
  backdrop-filter: blur(4px);
}

.dialog {
  width: min(100%, 34rem);
  display: grid;
  gap: 0.85rem;
  padding: 1.25rem;
  border-radius: var(--radius);
  border: 1px solid var(--border);
  background: var(--bg-elevated);
  box-shadow: var(--shadow);
}

header,
footer,
.row {
  display: flex;
  justify-content: space-between;
  gap: 0.75rem;
}

header h2 {
  margin: 0;
  font-family: var(--font-display);
  font-size: 1.3rem;
}

.row > label {
  flex: 1;
}

label {
  display: grid;
  gap: 0.3rem;
  color: var(--text-muted);
  font-size: 0.85rem;
}

.check {
  grid-template-columns: auto 1fr;
  align-items: center;
  color: var(--text);
}

input,
select,
textarea {
  width: 100%;
  padding: 0.55rem 0.7rem;
  border-radius: var(--radius-sm);
  border: 1px solid var(--border);
  background: var(--bg-soft);
  color: var(--text);
}

button {
  padding: 0.55rem 0.9rem;
  border-radius: var(--radius-sm);
  border: 1px solid transparent;
  background: var(--need);
  color: #071018;
  font-weight: 600;
  cursor: pointer;
}

button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.ghost {
  background: transparent;
  border-color: var(--border-strong);
  color: var(--text);
  font-weight: 500;
}
</style>
