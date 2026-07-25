<script setup lang="ts">
import { BUCKET_LABELS } from '../../types/bucket'
import type { CategorizationRule } from '../../types/rule'
import { formatCents } from '../../utils/money'

defineProps<{
  rules: CategorizationRule[]
}>()

const emit = defineEmits<{
  edit: [rule: CategorizationRule]
  remove: [rule: CategorizationRule]
}>()

function amountLabel(rule: CategorizationRule): string {
  if (rule.amount_cents_min == null && rule.amount_cents_max == null) {
    return 'Any amount'
  }

  const min = rule.amount_cents_min == null ? '…' : formatCents(rule.amount_cents_min)
  const max = rule.amount_cents_max == null ? '…' : formatCents(rule.amount_cents_max)
  return `${min} – ${max}`
}
</script>

<template>
  <ul class="list">
    <li v-for="rule in rules" :key="rule.id">
      <div>
        <strong>{{ rule.name }}</strong>
        <p>
          merchant contains “{{ rule.merchant_contains }}” ·
          {{ BUCKET_LABELS[rule.target_bucket]
          }}{{ rule.target_subcategory ? ` / ${rule.target_subcategory}` : '' }}
        </p>
        <p class="meta">
          priority {{ rule.priority }} · {{ amountLabel(rule) }} ·
          {{ rule.enabled ? 'enabled' : 'disabled' }} ·
          {{ rule.auto_review ? 'auto-review' : 'suggest only' }}
        </p>
      </div>
      <div class="actions">
        <button type="button" @click="emit('edit', rule)">Edit</button>
        <button type="button" class="danger" @click="emit('remove', rule)">Delete</button>
      </div>
    </li>
  </ul>
</template>

<style scoped>
.list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.75rem;
}

li {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: var(--bg-elevated);
}

strong {
  font-size: 1.05rem;
}

p {
  margin: 0.35rem 0 0;
  color: var(--text-muted);
  font-size: 0.9rem;
}

.meta {
  color: var(--text-dim);
  font-size: 0.82rem;
}

.actions {
  display: flex;
  gap: 0.5rem;
  align-items: start;
}

button {
  padding: 0.4rem 0.7rem;
  border-radius: var(--radius-sm);
  border: 1px solid var(--border-strong);
  background: transparent;
  color: var(--text);
  cursor: pointer;
}

.danger {
  border-color: rgba(240, 113, 120, 0.45);
  color: #ffb4b8;
}

@media (max-width: 700px) {
  li {
    flex-direction: column;
  }
}
</style>
