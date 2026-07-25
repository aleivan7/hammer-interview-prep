<script setup lang="ts">
import { onMounted, ref, shallowRef, watch } from 'vue'
import { fetchAccounts } from '../api/accountApi'
import {
  createTransaction,
  fetchTransactions,
  updateTransaction,
} from '../api/transactionApi'
import TransactionFilters from '../components/activity/TransactionFilters.vue'
import TransactionFormDialog from '../components/activity/TransactionFormDialog.vue'
import TransactionTable from '../components/activity/TransactionTable.vue'
import type { Account } from '../types/account'
import type { Bucket } from '../types/bucket'
import type { Transaction } from '../types/transaction'

const transactions = shallowRef<Transaction[]>([])
const accounts = shallowRef<Account[]>([])
const loading = shallowRef(true)
const saving = shallowRef(false)
const error = shallowRef<string | null>(null)
const dialogOpen = shallowRef(false)
const editing = shallowRef<Transaction | null>(null)

const search = ref('')
const bucket = ref<'' | Bucket>('')
const reviewed = ref<'' | 'true' | 'false'>('')

async function load(): Promise<void> {
  loading.value = true
  error.value = null

  try {
    transactions.value = await fetchTransactions({
      search: search.value || undefined,
      bucket: bucket.value || undefined,
      reviewed: reviewed.value === '' ? undefined : reviewed.value === 'true',
      paginate: false,
    })
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Failed to load activity.'
  } finally {
    loading.value = false
  }
}

function openCreate(): void {
  editing.value = null
  dialogOpen.value = true
}

function openEdit(transaction: Transaction): void {
  editing.value = transaction
  dialogOpen.value = true
}

async function handleSubmit(payload: {
  merchant: string
  amount_cents: number
  kind: Transaction['kind']
  transaction_date: string
  account_id: number | null
  bucket: Bucket | null
  subcategory: string | null
  notes: string | null
  reviewed?: boolean
}): Promise<void> {
  saving.value = true
  error.value = null

  try {
    if (editing.value) {
      await updateTransaction(editing.value.id, {
        merchant: payload.merchant,
        amount_cents: payload.amount_cents,
        kind: payload.kind,
        transaction_date: payload.transaction_date,
        account_id: payload.account_id,
        bucket: payload.bucket,
        subcategory: payload.subcategory,
        notes: payload.notes,
        reviewed: payload.reviewed,
      })
    } else {
      await createTransaction(payload)
    }

    dialogOpen.value = false
    editing.value = null
    await load()
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Failed to save transaction.'
  } finally {
    saving.value = false
  }
}

watch([search, bucket, reviewed], () => {
  void load()
})

onMounted(async () => {
  try {
    accounts.value = await fetchAccounts()
  } catch {
    accounts.value = []
  }

  await load()
})
</script>

<template>
  <div class="activity">
    <div class="toolbar">
      <TransactionFilters
        v-model:search="search"
        v-model:bucket="bucket"
        v-model:reviewed="reviewed"
      />
      <button type="button" class="primary" @click="openCreate">New transaction</button>
    </div>

    <p v-if="loading" class="status" role="status">Loading activity…</p>
    <p v-else-if="error" class="error" role="alert">{{ error }}</p>

    <TransactionTable
      v-if="!loading"
      :transactions="transactions"
      @edit="openEdit"
    />

    <TransactionFormDialog
      :open="dialogOpen"
      :accounts="accounts"
      :transaction="editing"
      :saving="saving"
      @close="dialogOpen = false"
      @submit="handleSubmit"
    />
  </div>
</template>

<style scoped>
.activity {
  display: grid;
  gap: 1rem;
  max-width: 72rem;
}

.toolbar {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 1rem;
  align-items: end;
}

.primary {
  padding: 0.6rem 1rem;
  border: 0;
  border-radius: var(--radius-sm);
  background: var(--need);
  color: #071018;
  font-weight: 600;
  cursor: pointer;
}

.status,
.error {
  margin: 0;
}

.error {
  color: var(--danger);
}
</style>
