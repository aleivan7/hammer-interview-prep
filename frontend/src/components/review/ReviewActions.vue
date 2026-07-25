<script setup lang="ts">
import type { Bucket } from '../../types/bucket'
import AppIcon from '../ui/AppIcon.vue'

defineProps<{
  updating: boolean
  canUndo: boolean
}>()

const emit = defineEmits<{
  categorize: [bucket: Bucket]
  undo: []
  skip: []
}>()
</script>

<template>
  <div class="actions">
    <div class="circles">
      <button
        type="button"
        class="circle want"
        :disabled="updating"
        aria-label="Want"
        @click="emit('categorize', 'want')"
      >
        <span class="ring"><AppIcon name="arrow-left" :size="20" /></span>
        <span>Wants <kbd>←</kbd></span>
      </button>
      <button
        type="button"
        class="circle savings"
        :disabled="updating"
        aria-label="Savings"
        @click="emit('categorize', 'savings')"
      >
        <span class="ring"><AppIcon name="arrow-down" :size="20" /></span>
        <span>Savings <kbd>↓</kbd></span>
      </button>
      <button
        type="button"
        class="circle need"
        :disabled="updating"
        aria-label="Need"
        @click="emit('categorize', 'need')"
      >
        <span class="ring"><AppIcon name="arrow-right" :size="20" /></span>
        <span>Needs <kbd>→</kbd></span>
      </button>
    </div>

    <div class="footer">
      <button
        type="button"
        class="btn btn-ghost"
        :disabled="updating || !canUndo"
        @click="emit('undo')"
      >
        <AppIcon name="undo" :size="16" />
        Undo <kbd>U</kbd>
      </button>
      <button type="button" class="btn btn-ghost" :disabled="updating" @click="emit('skip')">
        <AppIcon name="skip" :size="16" />
        Skip
      </button>
    </div>
  </div>
</template>

<style scoped>
.actions {
  display: grid;
  gap: var(--space-4);
}

.circles {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-3);
}

.circle {
  display: grid;
  justify-items: center;
  gap: var(--space-2);
  padding: var(--space-3);
  border: 0;
  background: transparent;
  color: var(--text);
  cursor: pointer;
}

.circle:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.ring {
  display: grid;
  place-items: center;
  width: 3.5rem;
  height: 3.5rem;
  border-radius: var(--radius-pill);
  border: 2px solid currentColor;
  background: var(--bg-soft);
}

.want {
  color: #fcd34d;
}

.want .ring {
  background: var(--want-soft);
}

.savings {
  color: var(--accent-text);
}

.savings .ring {
  background: var(--savings-soft);
}

.need {
  color: #93c5fd;
}

.need .ring {
  background: var(--need-soft);
}

.circle span {
  font-size: 0.72rem;
  font-weight: 600;
}

kbd {
  font-size: 0.68rem;
  color: var(--text-dim);
}

.footer {
  display: flex;
  justify-content: space-between;
  gap: var(--space-3);
}

@media (max-width: 640px) {
  .circles {
    grid-template-columns: 1fr;
  }
}
</style>
