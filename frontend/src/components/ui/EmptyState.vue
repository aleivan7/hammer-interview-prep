<script setup lang="ts">
import AppIcon, { type IconName } from './AppIcon.vue'

defineProps<{
  icon: IconName
  title: string
  body?: string
}>()
</script>

<template>
  <div class="empty panel">
    <div class="icon-wrap">
      <AppIcon :name="icon" :size="22" />
    </div>
    <h2>{{ title }}</h2>
    <p v-if="body">{{ body }}</p>
    <div v-if="$slots.default" class="actions">
      <slot />
    </div>
  </div>
</template>

<style scoped>
.empty {
  justify-items: center;
  text-align: center;
  padding: var(--space-8) var(--space-5);
}

.icon-wrap {
  display: grid;
  place-items: center;
  width: 3rem;
  height: 3rem;
  border-radius: var(--radius-pill);
  background: var(--bg-soft);
  color: var(--text-muted);
}

h2 {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 600;
}

p {
  margin: 0;
  color: var(--text-muted);
  font-size: 0.875rem;
  max-width: 24rem;
}

.actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
  justify-content: center;
}
</style>
