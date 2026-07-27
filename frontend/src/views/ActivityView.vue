<script setup lang="ts">
import { onMounted, shallowRef, watch } from 'vue'
import { useRoute } from 'vue-router'
import { fetchAccounts } from '../api/accountApi'
import { createCategory, fetchCategories } from '../api/categoryApi'
import {
  createTransaction,
  fetchTransactions,
  updateTransaction,
} from '../api/transactionApi'
import ActivitySummary from '../components/activity/ActivitySummary.vue'
import TransactionFeed from '../components/activity/TransactionFeed.vue'
import TransactionFilters from '../components/activity/TransactionFilters.vue'
import TransactionFormDialog from '../components/activity/TransactionFormDialog.vue'
import AppIcon from '../components/ui/AppIcon.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import type { Account } from '../types/account'
import type { Bucket } from '../types/bucket'
import type { Category } from '../types/category'
import type { Transaction } from '../types/transaction'
import { downloadTransactionsCsv } from '../utils/csv'

const route = useRoute()
const transactions = shallowRef<Transaction[]>([])
const accounts = shallowRef<Account[]>([])
const categories = shallowRef<Category[]>([])
const loading = shallowRef(true)
const saving = shallowRef(false)
const error = shallowRef<string | null>(null)
const dialogOpen = shallowRef(false)
const editing = shallowRef<Transaction | null>(null)

const search = shallowRef('')
const bucket = shallowRef<'' | Bucket>('')
const reviewed = shallowRef<'' | 'true' | 'false'>('')

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

function clearFilters(): void {
  search.value = ''
  bucket.value = ''
  reviewed.value = ''
}

async function handleCreateCategory(payload: { name: string; bucket: Bucket }): Promise<void> {
  try {
    const category = await createCategory(payload)
    if (!categories.value.some((item) => item.id === category.id)) {
      categories.value = [...categories.value, category]
    }
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Failed to create category.'
  }
}

async function handleSubmit(payload: {
  merchant: string
  amount_cents: number
  kind: Transaction['kind']
  transaction_date: string
  account_id: number | null
  bucket: Bucket | null
  category_id?: number | null
  subcategory?: string | null
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
        ...(payload.category_id === undefined
          ? {}
          : {
              category_id: payload.category_id,
              subcategory: payload.subcategory,
            }),
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
    const [loadedAccounts, loadedCategories] = await Promise.all([
      fetchAccounts(),
      fetchCategories(),
    ])
    accounts.value = loadedAccounts
    categories.value = loadedCategories
  } catch {
    accounts.value = []
    categories.value = []
  }

  await load()

  if (route?.query?.new === '1') {
    openCreate()
  }
})
</script>

<template>
  <div class="activity">
    <PageHeader title="Activity" subtitle="Your transactions at a glance.">
      <template #actions>
        <button
          type="button"
          class="btn btn-ghost"
          :disabled="!transactions.length"
          @click="downloadTransactionsCsv(transactions)"
        >
          <AppIcon name="download" :size="16" />
          Export
        </button>
        <button type="button" class="btn btn-primary" @click="openCreate">
          <AppIcon name="plus" :size="16" />
          New transaction
        </button>
      </template>
    </PageHeader>

    <TransactionFilters
      v-model:search="search"
      v-model:bucket="bucket"
      v-model:reviewed="reviewed"
    />

    <p v-if="loading" class="sr-only" role="status">Loading activity…</p>
    <p v-if="error" class="error" role="alert">{{ error }}</p>

    <div class="layout">
      <TransactionFeed
        v-if="!loading"
        :transactions="transactions"
        @edit="openEdit"
        @clear-filters="clearFilters"
      />
      <div v-else class="feed-skeleton panel">
        <div v-for="n in 6" :key="n" class="skel-row" />
      </div>

      <ActivitySummary
        :transactions="transactions"
        :accounts="accounts"
        :loading="loading"
      />
    </div>

    <TransactionFormDialog
      :open="dialogOpen"
      :accounts="accounts"
      :categories="categories"
      :transaction="editing"
      :saving="saving"
      @close="dialogOpen = false"
      @submit="handleSubmit"
      @create-category="handleCreateCategory"
    />
  </div>
</template>

<style scoped>
.activity {
  display: grid;
  gap: var(--space-5);
}

.layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 20rem;
  gap: var(--space-5);
  align-items: start;
}

.error {
  margin: 0;
  color: var(--danger);
}

.feed-skeleton {
  gap: var(--space-3);
}

.skel-row {
  height: 3.5rem;
  border-radius: var(--radius-sm);
  background: rgba(255, 255, 255, 0.04);
}

@media (max-width: 1180px) {
  .layout {
    grid-template-columns: 1fr;
  }
}
</style>
