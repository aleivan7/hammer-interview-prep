<script setup lang="ts">
import type { Bucket } from '../../types/bucket'

defineProps<{
  updating: boolean
  canUndo: boolean
}>()

const emit = defineEmits<{
  categorize: [bucket: Bucket]
  undo: []
}>()
</script>

<template>
  <div class="actions">
    <button type="button" class="want" :disabled="updating" @click="emit('categorize', 'want')">
      Want <kbd>←</kbd>
    </button>
    <button
      type="button"
      class="savings"
      :disabled="updating"
      @click="emit('categorize', 'savings')"
    >
      Savings <kbd>↓</kbd>
    </button>
    <button type="button" class="need" :disabled="updating" @click="emit('categorize', 'need')">
      Need <kbd>→</kbd>
    </button>
    <button type="button" class="undo" :disabled="updating || !canUndo" @click="emit('undo')">
      Undo <kbd>U</kbd>
    </button>
  </div>
</template>

<style scoped>
.actions {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.65rem;
}

button {
  display: grid;
  gap: 0.2rem;
  justify-items: center;
  padding: 0.75rem 0.5rem;
  border-radius: var(--radius-sm);
  border: 1px solid var(--border);
  background: var(--bg-soft);
  color: var(--text);
  cursor: pointer;
}

button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

kbd {
  font-size: 0.75rem;
  color: var(--text-dim);
}

.want {
  border-color: rgba(240, 193, 75, 0.45);
  background: var(--want-soft);
}

.need {
  border-color: rgba(79, 140, 255, 0.45);
  background: var(--need-soft);
}

.savings {
  border-color: rgba(62, 207, 142, 0.45);
  background: var(--savings-soft);
}

@media (max-width: 640px) {
  .actions {
    grid-template-columns: 1fr 1fr;
  }
}
</style>
