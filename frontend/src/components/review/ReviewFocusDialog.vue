<script setup lang="ts">
import { onMounted, onUnmounted, useTemplateRef } from 'vue'
import type { Bucket } from '../../types/bucket'
import type { Transaction, TransactionSuggestion } from '../../types/transaction'
import AppIcon from '../ui/AppIcon.vue'
import ReviewActions from './ReviewActions.vue'
import ReviewCard from './ReviewCard.vue'

const props = defineProps<{
  transaction: Transaction | null
  suggestion: TransactionSuggestion | null
  monthLabel: string
  progressLabel: string
  updating: boolean
  canUndo: boolean
  error: string | null
}>()

const emit = defineEmits<{
  close: []
  categorize: [bucket: Bucket]
  undo: []
  skip: []
}>()

const closeButton = useTemplateRef<HTMLButtonElement>('closeButton')
const previousBodyOverflow = document.body.style.overflow

onMounted(() => {
  document.body.style.overflow = 'hidden'
  closeButton.value?.focus()
})

onUnmounted(() => {
  document.body.style.overflow = previousBodyOverflow
})
</script>

<template>
  <div class="overlay" role="presentation" @click.self="!props.updating && emit('close')">
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
          :disabled="updating"
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
        <ReviewCard
          :transaction="transaction"
          :suggestion="suggestion"
          :updating="updating"
          @categorize="emit('categorize', $event)"
        />
      </div>

      <ReviewActions
        v-if="transaction"
        :updating="updating"
        :can-undo="canUndo"
        @categorize="emit('categorize', $event)"
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
            :disabled="updating || !canUndo"
            @click="emit('undo')"
          >
            <AppIcon name="undo" :size="16" />
            Undo
          </button>
          <button type="button" class="btn btn-primary" :disabled="updating" @click="emit('close')">
            Return to month
          </button>
        </div>
      </div>

      <p v-if="error" class="inline-error" role="alert">{{ error }}</p>
      <p v-if="updating" class="status" role="status">Saving category…</p>
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
  background: rgba(0, 0, 0, 0.72);
  backdrop-filter: blur(8px);
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

.inline-error {
  color: var(--danger);
}

.complete {
  display: grid;
  justify-items: center;
  gap: var(--space-3);
  padding: var(--space-6) var(--space-4);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: var(--bg-elevated);
  text-align: center;
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

@media (max-height: 760px) {
  .overlay {
    place-items: start center;
  }
}
</style>
