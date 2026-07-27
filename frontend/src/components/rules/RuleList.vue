<script setup lang="ts">
import { BUCKET_LABELS } from '../../types/bucket'
import type { CategorizationRule } from '../../types/rule'
import { formatCents } from '../../utils/money'
import AppIcon from '../ui/AppIcon.vue'
import BucketPill from '../ui/BucketPill.vue'

defineProps<{
  rules: CategorizationRule[]
}>()

const emit = defineEmits<{
  edit: [rule: CategorizationRule]
  remove: [rule: CategorizationRule]
}>()

function merchantLabel(rule: CategorizationRule): string {
  return rule.canonical_merchant?.name ?? rule.merchant_contains ?? 'Unknown merchant'
}

function categoryLabel(rule: CategorizationRule): string {
  return rule.target_category?.name ?? rule.target_subcategory ?? 'Uncategorized'
}

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
  <div class="panel list">
    <ul class="panel-rows">
      <li v-for="rule in rules" :key="rule.id" class="row">
        <div class="copy">
          <strong>{{ rule.name }}</strong>
          <p>
            {{ merchantLabel(rule) }} → {{ categoryLabel(rule) }} · {{ amountLabel(rule) }}
          </p>
        </div>

        <div class="middle">
          <BucketPill :bucket="rule.target_bucket" />
          <span class="sub">{{ categoryLabel(rule) }}</span>
          <span class="sr-only">{{ BUCKET_LABELS[rule.target_bucket] }}</span>
        </div>

        <div class="meta">
          <span class="pill">P{{ rule.priority }}</span>
          <span class="pill" :class="{ 'pill-accent': rule.enabled }">
            {{ rule.enabled ? 'Enabled' : 'Disabled' }}
          </span>
          <span class="hint">{{ rule.auto_review ? 'auto-review' : 'suggest only' }}</span>
        </div>

        <div class="actions">
          <button
            type="button"
            class="btn btn-icon"
            aria-label="Edit rule"
            @click="emit('edit', rule)"
          >
            <AppIcon name="pencil" :size="16" />
            <span class="sr-only">Edit</span>
          </button>
          <button
            type="button"
            class="btn btn-icon danger"
            aria-label="Delete rule"
            @click="emit('remove', rule)"
          >
            <AppIcon name="trash" :size="16" />
            <span class="sr-only">Delete</span>
          </button>
        </div>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.list {
  gap: 0;
  padding: 0 var(--space-4);
}

.row {
  display: grid;
  grid-template-columns: minmax(0, 1.4fr) auto minmax(0, 1fr) auto;
  gap: var(--space-4);
  align-items: center;
  padding: var(--space-4) 0;
}

.copy strong {
  font-size: 0.875rem;
  font-weight: 600;
}

.copy p {
  margin: 0.25rem 0 0;
  color: var(--text-muted);
  font-size: 0.75rem;
}

.middle {
  display: grid;
  gap: 0.25rem;
  justify-items: start;
}

.sub {
  color: var(--text-dim);
  font-size: 0.72rem;
}

.meta {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
  align-items: center;
}

.hint {
  color: var(--text-dim);
  font-size: 0.72rem;
}

.actions {
  display: flex;
  gap: var(--space-2);
}

.danger {
  color: #fca5a5;
}

@media (max-width: 900px) {
  .row {
    grid-template-columns: 1fr;
    gap: var(--space-3);
  }

  .actions {
    justify-content: end;
  }
}
</style>
