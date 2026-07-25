<script setup lang="ts">
import { onMounted, onUnmounted, useTemplateRef } from 'vue'

const props = defineProps<{
  busy?: boolean
  error?: string | null
}>()

const emit = defineEmits<{
  confirm: []
  cancel: []
}>()

const confirmButton = useTemplateRef<HTMLButtonElement>('confirmButton')

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape' && !props.busy) {
    emit('cancel')
  }
}

onMounted(() => {
  document.body.style.overflow = 'hidden'
  window.addEventListener('keydown', onKeydown)
  confirmButton.value?.focus()
})

onUnmounted(() => {
  document.body.style.overflow = ''
  window.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <div class="overlay" role="presentation" @click.self="!busy && emit('cancel')">
    <div
      class="dialog panel"
      role="dialog"
      aria-modal="true"
      aria-labelledby="reset-demo-title"
      aria-describedby="reset-demo-copy"
    >
      <h2 id="reset-demo-title">Reset this demo profile?</h2>
      <p id="reset-demo-copy">
        This will restore the original accounts, transactions, rules, and financial plan for this
        fictional user.
      </p>

      <p v-if="error" class="error" role="alert">{{ error }}</p>

      <div class="actions">
        <button type="button" class="btn btn-ghost" :disabled="busy" @click="emit('cancel')">
          Cancel
        </button>
        <button
          ref="confirmButton"
          type="button"
          class="btn btn-primary"
          :disabled="busy"
          @click="emit('confirm')"
        >
          {{ busy ? 'Resetting…' : 'Reset demo data' }}
        </button>
      </div>
    </div>
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
  background: rgba(0, 0, 0, 0.65);
}

.dialog {
  width: min(28rem, 100%);
  gap: var(--space-4);
  padding: var(--space-5);
}

.dialog h2 {
  margin: 0;
  font-size: 1.2rem;
}

.dialog p {
  margin: 0;
  color: var(--text-muted);
  line-height: 1.45;
}

.error {
  color: #fca5a5 !important;
}

.actions {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-3);
  flex-wrap: wrap;
}
</style>
