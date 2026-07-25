<script setup lang="ts">
import { BUCKET_LABELS, type Bucket } from '../../types/bucket'
import type { Transaction, TransactionSuggestion } from '../../types/transaction'
import { formatDate, formatDollars } from '../../utils/money'
import { useCardSwipe } from '../../composables/useCardSwipe'

const props = defineProps<{
  transaction: Transaction
  suggestion: TransactionSuggestion | null
  updating: boolean
}>()

const emit = defineEmits<{
  categorize: [bucket: Bucket]
}>()

const { hint, cardStyle, onPointerDown, onPointerMove, onPointerUp, onPointerCancel } =
  useCardSwipe((bucket) => {
    if (!props.updating) {
      emit('categorize', bucket)
    }
  })
</script>

<template>
  <article
    class="card"
    :class="{ disabled: updating, hint: hint ?? undefined }"
    :style="cardStyle"
    :data-hint="hint ?? undefined"
    @pointerdown="onPointerDown"
    @pointermove="onPointerMove"
    @pointerup="onPointerUp"
    @pointercancel="onPointerCancel"
  >
    <p v-if="hint" class="hint-label">{{ BUCKET_LABELS[hint] }}</p>
    <header>
      <h2>{{ transaction.merchant }}</h2>
      <p class="amount">{{ formatDollars(transaction.amount) }}</p>
    </header>

    <dl>
      <div>
        <dt>Date</dt>
        <dd>{{ formatDate(transaction.transaction_date) }}</dd>
      </div>
      <div>
        <dt>Kind</dt>
        <dd>{{ transaction.kind }}</dd>
      </div>
    </dl>

    <div v-if="suggestion" class="suggestion">
      <p>
        Suggestion:
        <strong>{{ suggestion.bucket ? BUCKET_LABELS[suggestion.bucket] : 'None' }}</strong>
        · {{ suggestion.confidence }}% · {{ suggestion.source }}
      </p>
      <p class="explain">{{ suggestion.explanation }}</p>
    </div>

    <p class="gesture-help">Drag left Wants · right Needs · down Savings</p>
  </article>
</template>

<style scoped>
.card {
  position: relative;
  touch-action: none;
  user-select: none;
  display: grid;
  gap: 1rem;
  padding: 1.5rem;
  border-radius: var(--radius-lg);
  border: 1px solid var(--border);
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.03), transparent),
    var(--bg-elevated);
  box-shadow: var(--shadow);
  cursor: grab;
}

.card:active {
  cursor: grabbing;
}

.card.disabled {
  opacity: 0.7;
  pointer-events: none;
}

.card[data-hint='need'] {
  box-shadow: 0 0 0 2px var(--need), var(--shadow);
}

.card[data-hint='want'] {
  box-shadow: 0 0 0 2px var(--want), var(--shadow);
}

.card[data-hint='savings'] {
  box-shadow: 0 0 0 2px var(--savings), var(--shadow);
}

.hint-label {
  position: absolute;
  top: 0.85rem;
  right: 1rem;
  margin: 0;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  font-size: 0.78rem;
}

header {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  align-items: baseline;
}

h2 {
  margin: 0;
  font-family: var(--font-display);
  font-size: 1.7rem;
}

.amount {
  margin: 0;
  font-size: 1.35rem;
  font-variant-numeric: tabular-nums;
}

dl {
  display: grid;
  gap: 0.65rem;
  margin: 0;
}

dl div {
  display: grid;
  gap: 0.15rem;
}

dt {
  color: var(--text-dim);
  font-size: 0.8rem;
}

dd {
  margin: 0;
}

.suggestion {
  padding: 0.8rem;
  border-radius: var(--radius-sm);
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid var(--border);
}

.suggestion p {
  margin: 0;
}

.explain {
  margin-top: 0.35rem !important;
  color: var(--text-muted);
  font-size: 0.9rem;
}

.gesture-help {
  margin: 0;
  color: var(--text-dim);
  font-size: 0.82rem;
}
</style>
