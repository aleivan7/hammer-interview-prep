<script setup lang="ts">
import { computed, shallowRef, watch } from 'vue'
import { BUCKET_LABELS, type Bucket } from '../../types/bucket'
import type { Category } from '../../types/category'
import type { Transaction, TransactionSuggestion } from '../../types/transaction'
import { formatDate } from '../../utils/money'
import {
  displayMerchantName,
  formatSigned,
  hasDistinctRawDescriptor,
  rawMerchantDescriptor,
  signedAmountCents,
} from '../../utils/transactions'
import { useCardSwipe } from '../../composables/useCardSwipe'
import MerchantAvatar from '../ui/MerchantAvatar.vue'

const props = defineProps<{
  transaction: Transaction
  suggestion: TransactionSuggestion | null
  categories: Category[]
  updating: boolean
}>()

const emit = defineEmits<{
  categorize: [payload: { bucket: Bucket; category_id?: number | null }]
  exitStart: []
}>()

const pendingCategoryId = shallowRef<number | null>(null)

const {
  hint,
  cardStyle,
  exiting,
  onPointerDown,
  onPointerMove,
  onPointerUp,
  onPointerCancel,
  beginExit,
  reset,
} = useCardSwipe((bucket) => {
  if (props.updating) {
    return false
  }

  emit('categorize', {
    bucket,
    ...(pendingCategoryId.value != null
      ? { category_id: pendingCategoryId.value }
      : {}),
  })
  pendingCategoryId.value = null
  return true
})

const groupedCategories = computed(() => {
  const buckets: Bucket[] = ['need', 'want', 'savings']
  return buckets.map((bucket) => ({
    bucket,
    label: BUCKET_LABELS[bucket],
    categories: props.categories.filter(
      (category) => category.bucket === bucket && !category.archived_at,
    ),
  }))
})

watch(exiting, (isExiting) => {
  if (isExiting) {
    emit('exitStart')
  }
})

async function exitThenCategorize(
  bucket: Bucket,
  categoryId: number | null = null,
): Promise<void> {
  if (props.updating || exiting.value) {
    return
  }

  pendingCategoryId.value = categoryId
  await beginExit(bucket)
}

function onSelectChange(event: Event): void {
  const value = (event.target as HTMLSelectElement).value as Bucket | ''
  if (value) {
    void exitThenCategorize(value)
  }
}

function onCategoryChange(event: Event): void {
  const value = (event.target as HTMLSelectElement).value
  if (!value) {
    return
  }

  const category = props.categories.find((item) => item.id === Number(value))
  if (!category) {
    return
  }

  void exitThenCategorize(category.bucket, category.id)
}

defineExpose({
  beginExit: (bucket: Bucket) => exitThenCategorize(bucket),
  reset,
  exiting,
})
</script>

<template>
  <article
    class="card"
    :class="{ disabled: updating || exiting, hint: hint ?? undefined }"
    :style="cardStyle"
    :data-hint="hint ?? undefined"
    @pointerdown="onPointerDown"
    @pointermove="onPointerMove"
    @pointerup="onPointerUp"
    @pointercancel="onPointerCancel"
  >
    <p v-if="hint" class="hint-label">{{ BUCKET_LABELS[hint] }}</p>

    <MerchantAvatar :name="displayMerchantName(transaction)" :size="56" />
    <h2>{{ displayMerchantName(transaction) }}</h2>
    <p v-if="hasDistinctRawDescriptor(transaction)" class="raw">
      {{ rawMerchantDescriptor(transaction) }}
    </p>
    <p class="date">{{ formatDate(transaction.transaction_date) }}</p>
    <p class="amount money" :class="signedAmountCents(transaction) > 0 ? 'credit' : 'debit'">
      {{ formatSigned(signedAmountCents(transaction)) }}
    </p>

    <label class="bucket-select">
      <span class="sr-only">Bucket</span>
      <select
        :value="suggestion?.bucket ?? ''"
        :disabled="updating || exiting"
        @change="onSelectChange"
        @pointerdown.stop
      >
        <option value="" disabled>Choose bucket</option>
        <option value="need">Needs</option>
        <option value="want">Wants</option>
        <option value="savings">Savings</option>
      </select>
    </label>

    <label class="category-select">
      <span class="sr-only">Detailed category</span>
      <select
        value=""
        :disabled="updating || exiting"
        @change="onCategoryChange"
        @pointerdown.stop
      >
        <option value="">Optional category…</option>
        <optgroup
          v-for="group in groupedCategories"
          :key="group.bucket"
          :label="group.label"
        >
          <option
            v-for="category in group.categories"
            :key="category.id"
            :value="category.id"
          >
            {{ category.name }}
          </option>
        </optgroup>
      </select>
    </label>

    <div class="suggestion" :class="{ empty: !suggestion }">
      <template v-if="suggestion">
        <p>
          Suggestion:
          <strong>{{ suggestion.bucket ? BUCKET_LABELS[suggestion.bucket] : 'None' }}</strong>
          · {{ suggestion.confidence }}% · {{ suggestion.source }}
        </p>
        <p class="explain">{{ suggestion.explanation }}</p>
      </template>
      <p v-else class="explain">Looking for a suggestion…</p>
    </div>
  </article>
</template>

<style scoped>
.card {
  position: relative;
  touch-action: none;
  user-select: none;
  display: grid;
  justify-items: center;
  gap: var(--space-3);
  padding: var(--space-6) var(--space-5);
  border-radius: var(--radius-lg);
  border: 1px solid var(--border);
  background: var(--bg-elevated);
  box-shadow: var(--shadow-panel);
  cursor: grab;
  text-align: center;
  will-change: transform, opacity;
}

.card:active {
  cursor: grabbing;
}

.card.disabled {
  opacity: 0.7;
  pointer-events: none;
}

.card[data-hint='need'] {
  box-shadow: 0 0 0 2px var(--need), var(--shadow-panel);
}

.card[data-hint='want'] {
  box-shadow: 0 0 0 2px var(--want), var(--shadow-panel);
}

.card[data-hint='savings'] {
  box-shadow: 0 0 0 2px var(--savings), var(--shadow-panel);
}

.hint-label {
  position: absolute;
  top: 0.85rem;
  right: 1rem;
  margin: 0;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  font-size: 0.72rem;
}

h2 {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 600;
}

.raw,
.date {
  margin: 0;
  color: var(--text-muted);
  font-size: 0.78rem;
}

.raw {
  color: var(--text-dim);
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}

.amount {
  margin: 0;
  font-size: 1.75rem;
  font-weight: 700;
}

.bucket-select select,
.category-select select {
  min-height: 2.25rem;
  padding: 0.4rem 0.8rem;
  border-radius: var(--radius-pill);
  border: 1px solid var(--border-strong);
  background: var(--bg-soft);
  color: var(--text);
}

.category-select select {
  border-radius: var(--radius-sm);
  max-width: 16rem;
}

.suggestion {
  width: 100%;
  min-height: 4.25rem;
  padding: var(--space-3);
  border-radius: var(--radius-sm);
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid var(--border);
  text-align: left;
}

.suggestion.empty {
  display: grid;
  align-content: center;
}

.suggestion p {
  margin: 0;
  font-size: 0.75rem;
}

.explain {
  margin-top: 0.35rem !important;
  color: var(--text-muted);
}
</style>
