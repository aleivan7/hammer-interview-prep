<script setup lang="ts">
import type { Transaction, TransactionCategory } from '../types/transaction'
import CategoryButton from './CategoryButton.vue'

const props = defineProps<{
  transaction: Transaction
  updating: boolean
}>()

const emit = defineEmits<{
  categorize: [category: TransactionCategory]
}>()

const categoryOptions: { value: TransactionCategory; label: string }[] = [
  { value: 'need', label: 'Need' },
  { value: 'want', label: 'Want' },
  { value: 'debt_savings', label: 'Debt / Savings' },
]

function formatCurrency(amount: string): string {
  const value = Number(amount)

  if (Number.isNaN(value)) {
    return amount
  }

  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(value)
}

function formatDate(isoDate: string): string {
  // Append noon so the calendar day does not shift across time zones.
  const date = new Date(`${isoDate}T12:00:00`)

  if (Number.isNaN(date.getTime())) {
    return isoDate
  }

  return new Intl.DateTimeFormat('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  }).format(date)
}

function categoryLabel(category: Transaction['category']): string {
  if (!category) {
    return 'Uncategorized'
  }

  return categoryOptions.find((option) => option.value === category)?.label ?? category
}
</script>

<template>
  <article class="transaction-card" aria-live="polite">
    <header class="header">
      <h2 class="merchant">{{ props.transaction.merchant }}</h2>
      <p class="amount">{{ formatCurrency(props.transaction.amount) }}</p>
    </header>

    <dl class="details">
      <div>
        <dt>Transaction date</dt>
        <dd>{{ formatDate(props.transaction.transaction_date) }}</dd>
      </div>
      <div>
        <dt>Current category</dt>
        <dd>{{ categoryLabel(props.transaction.category) }}</dd>
      </div>
    </dl>

    <fieldset class="categories" :disabled="props.updating">
      <legend>Choose a category</legend>
      <div class="category-row" role="group" aria-label="Transaction categories">
        <CategoryButton
          v-for="option in categoryOptions"
          :key="option.value"
          :label="option.label"
          :disabled="props.updating"
          @select="emit('categorize', option.value)"
        />
      </div>
    </fieldset>
  </article>
</template>

<style scoped>
.transaction-card {
  display: grid;
  gap: 1.25rem;
  padding: 1.5rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.5rem;
  background: #ffffff;
  text-align: left;
}

.header {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  align-items: baseline;
}

.merchant {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 700;
  color: #0f172a;
}

.amount {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
}

.details {
  display: grid;
  gap: 0.75rem;
  margin: 0;
}

.details div {
  display: grid;
  gap: 0.15rem;
}

.details dt {
  font-size: 0.85rem;
  color: #64748b;
}

.details dd {
  margin: 0;
  color: #0f172a;
}

.categories {
  margin: 0;
  padding: 0;
  border: 0;
  min-inline-size: 0;
}

.categories legend {
  margin-bottom: 0.75rem;
  font-weight: 600;
  color: #0f172a;
}

.category-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}
</style>
