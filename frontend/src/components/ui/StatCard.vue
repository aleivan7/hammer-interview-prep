<script setup lang="ts">
import AppIcon, { type IconName } from './AppIcon.vue'

defineProps<{
  label: string
  value: string
  context?: string
  icon: IconName
  tone?: 'accent' | 'need' | 'want' | 'neutral'
}>()
</script>

<template>
  <article class="stat" :data-tone="tone ?? 'neutral'">
    <div class="icon-wrap">
      <AppIcon :name="icon" :size="18" />
    </div>
    <div class="copy">
      <p class="label">{{ label }}</p>
      <p class="value money">{{ value }}</p>
      <p v-if="context" class="context">{{ context }}</p>
    </div>
  </article>
</template>

<style scoped>
.stat {
  display: flex;
  gap: var(--space-3);
  align-items: start;
  padding: var(--space-4) 0;
}

.icon-wrap {
  display: grid;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-pill);
  background: var(--bg-soft);
  color: var(--text-muted);
}

.stat[data-tone='accent'] .icon-wrap {
  background: var(--accent-soft);
  color: var(--accent-text);
}

.stat[data-tone='need'] .icon-wrap {
  background: var(--need-soft);
  color: #93c5fd;
}

.stat[data-tone='want'] .icon-wrap {
  background: var(--want-soft);
  color: #fcd34d;
}

.copy {
  display: grid;
  gap: 0.15rem;
  min-width: 0;
}

.label {
  margin: 0;
  color: var(--text-muted);
  font-size: 0.8125rem;
}

.value {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 600;
}

.context {
  margin: 0;
  color: var(--text-dim);
  font-size: 0.75rem;
}
</style>
