<script setup lang="ts">
/**
 * Accessible category choice button.
 * Disabled while a PATCH is in flight so the user cannot double-submit.
 */
defineProps<{
  label: string
  disabled?: boolean
}>()

defineEmits<{
  select: []
}>()
</script>

<template>
  <button
    type="button"
    class="category-button"
    :disabled="disabled"
    :aria-disabled="disabled ? 'true' : 'false'"
    @click="$emit('select')"
  >
    {{ label }}
  </button>
</template>

<style scoped>
.category-button {
  flex: 1;
  min-width: 7rem;
  padding: 0.75rem 1rem;
  border: 1px solid #334155;
  border-radius: 0.375rem;
  background: #f8fafc;
  color: #0f172a;
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}

.category-button:hover:not(:disabled) {
  background: #e2e8f0;
}

.category-button:focus-visible {
  outline: 2px solid #2563eb;
  outline-offset: 2px;
}

.category-button:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
</style>
