<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    name: string
    size?: 36 | 40 | 56
  }>(),
  { size: 40 },
)

const hues = [160, 200, 220, 260, 30, 340, 140]

const initials = computed(() => {
  const parts = props.name.trim().split(/\s+/).filter(Boolean)
  if (!parts.length) {
    return '?'
  }
  if (parts.length === 1) {
    return parts[0].slice(0, 2).toUpperCase()
  }
  return `${parts[0][0]}${parts[1][0]}`.toUpperCase()
})

const background = computed(() => {
  let hash = 0
  for (let i = 0; i < props.name.length; i += 1) {
    hash = (hash + props.name.charCodeAt(i) * (i + 1)) % 997
  }
  const hue = hues[hash % hues.length]
  return `hsla(${hue}, 42%, 28%, 0.85)`
})
</script>

<template>
  <span
    class="avatar"
    :style="{ width: `${size}px`, height: `${size}px`, background, fontSize: `${size * 0.32}px` }"
    aria-hidden="true"
  >
    {{ initials }}
  </span>
</template>

<style scoped>
.avatar {
  display: inline-grid;
  place-items: center;
  border-radius: var(--radius-pill);
  color: #f8fafc;
  font-weight: 600;
  letter-spacing: 0.02em;
  flex: 0 0 auto;
}
</style>
