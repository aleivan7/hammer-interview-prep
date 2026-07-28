<script setup lang="ts">
import { computed, onMounted, onUnmounted, shallowRef, useTemplateRef, watch } from 'vue'
import type { Bucket } from '../../types/bucket'
import type { Category } from '../../types/category'
import type { Transaction, TransactionSuggestion } from '../../types/transaction'
import AppIcon from '../ui/AppIcon.vue'
import ReviewActions from './ReviewActions.vue'
import ReviewCard from './ReviewCard.vue'

export type ReviewCategorizePayload = {
  bucket: Bucket
  category_id?: number | null
}

const props = defineProps<{
  transaction: Transaction | null
  suggestion: TransactionSuggestion | null
  categories: Category[]
  monthLabel: string
  progressLabel: string
  updating: boolean
  canUndo: boolean
  error: string | null
}>()

const emit = defineEmits<{
  close: []
  categorize: [payload: ReviewCategorizePayload]
  undo: []
  skip: []
}>()

type ReviewCardExpose = {
  beginExit: (bucket: Bucket) => Promise<void>
  reset: () => void
}

const closeButton = useTemplateRef<HTMLButtonElement>('closeButton')
const cardRef = useTemplateRef<ReviewCardExpose>('cardRef')
const previousBodyOverflow = document.body.style.overflow
const exiting = shallowRef(false)

const busy = computed(() => props.updating || exiting.value)

async function requestCategorize(bucket: Bucket): Promise<void> {
  if (!props.transaction || busy.value) {
    return
  }

  exiting.value = true
  try {
    if (cardRef.value) {
      await cardRef.value.beginExit(bucket)
    } else {
      emit('categorize', { bucket })
    }
  } finally {
    exiting.value = false
  }
}

function onCardCategorize(payload: ReviewCategorizePayload): void {
  emit('categorize', payload)
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    if (busy.value) {
      return
    }
    event.preventDefault()
    emit('close')
    return
  }

  const target = event.target as HTMLElement | null
  if (target && ['A', 'INPUT', 'SELECT', 'TEXTAREA'].includes(target.tagName)) {
    return
  }

  if (event.key.toLowerCase() === 'u') {
    event.preventDefault()
    if (!busy.value) {
      emit('undo')
    }
    return
  }

  if (busy.value || !props.transaction) {
    return
  }

  if (event.key === 'ArrowLeft') {
    event.preventDefault()
    void requestCategorize('want')
  } else if (event.key === 'ArrowRight') {
    event.preventDefault()
    void requestCategorize('need')
  } else if (event.key === 'ArrowDown') {
    event.preventDefault()
    void requestCategorize('savings')
  }
}

watch(
  () => props.error,
  (error) => {
    if (error) {
      cardRef.value?.reset()
      exiting.value = false
    }
  },
)

watch(
  () => props.transaction?.id,
  () => {
    exiting.value = false
  },
)

function onExitStart(): void {
  exiting.value = true
}

onMounted(() => {
  document.body.style.overflow = 'hidden'
  closeButton.value?.focus()
  window.addEventListener('keydown', onKeydown)
})

onUnmounted(() => {
  document.body.style.overflow = previousBodyOverflow
  window.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <div class="overlay" role="presentation" @click.self="!busy && emit('close')">
    <section
      class="dialog"
      role="dialog"
      aria-modal="true"
      aria-labelledby="review-focus-title"
      aria-describedby="review-focus-progress"
    >
      <header class="dialog-header">
        <div>
          <p class="eyebrow">{{ monthLabel }}</p>
          <h2 id="review-focus-title">Focus mode</h2>
          <p id="review-focus-progress" class="progress" aria-live="polite">
            {{ progressLabel }}
          </p>
        </div>
        <button
          ref="closeButton"
          type="button"
          class="btn btn-icon"
          aria-label="Close focus mode"
          :disabled="busy"
          @click="emit('close')"
        >
          <AppIcon name="close" :size="18" />
        </button>
      </header>

      <div v-if="transaction" class="stack">
        <div class="deck" aria-hidden="true">
          <div class="back-card one" />
          <div class="back-card two" />
        </div>
        <Transition name="focus-card" mode="out-in">
          <ReviewCard
            :key="transaction.id"
            ref="cardRef"
            :transaction="transaction"
            :suggestion="suggestion"
            :categories="categories"
            :updating="updating"
            @categorize="onCardCategorize"
            @exit-start="onExitStart"
          />
        </Transition>
      </div>

      <ReviewActions
        v-if="transaction"
        :updating="busy"
        :can-undo="canUndo && !exiting"
        @categorize="requestCategorize"
        @undo="emit('undo')"
        @skip="emit('skip')"
      />

      <div v-else class="complete" role="status">
        <AppIcon name="check" :size="32" />
        <h3>Month complete</h3>
        <p>Every transaction in this Focus session has been reviewed.</p>
        <div class="complete-actions">
          <button
            type="button"
            class="btn btn-ghost"
            :disabled="busy || !canUndo"
            @click="emit('undo')"
          >
            <AppIcon name="undo" :size="16" />
            Undo
          </button>
          <button type="button" class="btn btn-primary" :disabled="busy" @click="emit('close')">
            Return to month
          </button>
        </div>
      </div>

      <div class="feedback" aria-live="polite">
        <p v-if="error" class="inline-error" role="alert">{{ error }}</p>
        <p v-else-if="updating" class="status" role="status">Saving category…</p>
        <p v-else class="status placeholder">&nbsp;</p>
      </div>
    </section>
  </div>
</template>

<style scoped>
.overlay {
  position: fixed;
  inset: 0;
  z-index: 40;
  display: grid;
  place-items: center;
  padding: var(--space-4);
  overflow-y: auto;
  background: rgba(0, 0, 0, 0.78);
}

.dialog {
  display: grid;
  gap: var(--space-4);
  width: min(100%, 30rem);
  padding: var(--space-5);
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-lg);
  background: var(--bg);
  box-shadow: var(--shadow-modal);
}

.dialog-header {
  display: flex;
  align-items: start;
  justify-content: space-between;
  gap: var(--space-4);
}

.dialog-header h2,
.eyebrow,
.progress,
.inline-error,
.status {
  margin: 0;
}

.dialog-header h2 {
  font-size: 1.25rem;
}

.eyebrow {
  color: var(--accent-text);
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.progress,
.status {
  color: var(--text-muted);
  font-size: 0.8125rem;
}

.stack {
  position: relative;
  min-height: 22rem;
}

.deck {
  position: absolute;
  inset: 0.75rem 0.75rem auto;
  height: 8rem;
  pointer-events: none;
}

.back-card {
  position: absolute;
  inset: 0;
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  background: var(--bg-soft);
}

.back-card.one {
  opacity: 0.7;
  transform: translateY(10px) scale(0.98);
}

.back-card.two {
  opacity: 0.45;
  transform: translateY(18px) scale(0.96);
}

.feedback {
  min-height: 1.25rem;
}

.inline-error {
  color: var(--danger);
}

.status.placeholder {
  visibility: hidden;
}

.complete {
  display: grid;
  justify-items: center;
  gap: var(--space-3);
  min-height: 22rem;
  padding: var(--space-6) var(--space-4);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: var(--bg-elevated);
  text-align: center;
  align-content: center;
}

.complete h3,
.complete p {
  margin: 0;
}

.complete h3 {
  font-size: 1.125rem;
}

.complete p {
  color: var(--text-muted);
  font-size: 0.875rem;
}

.complete-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: var(--space-3);
}

.focus-card-enter-active {
  transition:
    transform 140ms ease,
    opacity 140ms ease;
}

.focus-card-leave-active {
  position: absolute;
  inset: 0;
  width: 100%;
  transition: opacity 60ms ease;
}

.focus-card-enter-from {
  transform: translateY(12px) scale(0.985);
  opacity: 0;
}

.focus-card-leave-to {
  opacity: 0;
}

@media (max-height: 760px) {
  .overlay {
    place-items: start center;
  }
}
</style>
