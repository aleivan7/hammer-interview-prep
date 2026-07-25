<script setup lang="ts">
import { BUCKET_LABELS, type Bucket } from '../../types/bucket'

defineProps<{
  bucket: Bucket | null
}>()
</script>

<template>
  <span class="pill" :data-bucket="bucket ?? 'none'">
    <span class="dot" aria-hidden="true" />
    {{ bucket ? BUCKET_LABELS[bucket] : 'Uncategorized' }}
  </span>
</template>

<style scoped>
.pill {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.2rem 0.55rem;
  border-radius: var(--radius-pill);
  border: 1px solid var(--border);
  background: var(--bg-soft);
  color: var(--text-muted);
  font-size: 0.72rem;
  font-weight: 600;
  white-space: nowrap;
}

.dot {
  width: 0.4rem;
  height: 0.4rem;
  border-radius: var(--radius-pill);
  background: currentColor;
}

.pill[data-bucket='need'] {
  color: #93c5fd;
  background: var(--need-soft);
  border-color: rgba(59, 130, 246, 0.3);
}

.pill[data-bucket='want'] {
  color: #fcd34d;
  background: var(--want-soft);
  border-color: rgba(245, 158, 11, 0.3);
}

.pill[data-bucket='savings'] {
  color: var(--accent-text);
  background: var(--savings-soft);
  border-color: rgba(34, 197, 94, 0.3);
}
</style>
