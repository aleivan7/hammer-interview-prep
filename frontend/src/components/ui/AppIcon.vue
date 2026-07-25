<script setup lang="ts">
import { computed } from 'vue'

export type IconName =
  | 'dashboard'
  | 'activity'
  | 'review'
  | 'rules'
  | 'search'
  | 'filter'
  | 'download'
  | 'plus'
  | 'close'
  | 'chevron-right'
  | 'chevron-down'
  | 'arrow-left'
  | 'arrow-right'
  | 'arrow-down'
  | 'undo'
  | 'skip'
  | 'info'
  | 'gem'
  | 'alert'
  | 'trending'
  | 'star'
  | 'bank'
  | 'tag'
  | 'check'
  | 'pencil'
  | 'trash'
  | 'list'
  | 'target'

const props = withDefaults(
  defineProps<{
    name: IconName
    size?: number
  }>(),
  { size: 18 },
)

const paths: Record<IconName, string> = {
  dashboard:
    'M4 4h7v7H4V4zm9 0h7v4h-7V4zM4 13h7v7H4v-7zm9-3h7v10h-7V10z',
  activity:
    'M4 6h16M4 12h10M4 18h14',
  review:
    'M8 4h8a2 2 0 0 1 2 2v12l-6-3-6 3V6a2 2 0 0 1 2-2z',
  rules:
    'M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01',
  search:
    'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zm9 1-4.35-4.35',
  filter:
    'M4 6h16M7 12h10M10 18h4',
  download:
    'M12 4v10m0 0 4-4m-4 4-4-4M5 18h14',
  plus: 'M12 5v14M5 12h14',
  close: 'M6 6l12 12M18 6 6 18',
  'chevron-right': 'M9 6l6 6-6 6',
  'chevron-down': 'M6 9l6 6 6-6',
  'arrow-left': 'M19 12H5m0 0 6-6m-6 6 6 6',
  'arrow-right': 'M5 12h14m0 0-6-6m6 6-6 6',
  'arrow-down': 'M12 5v14m0 0 6-6m-6 6-6-6',
  undo: 'M9 14 4 9l5-5M4 9h10a6 6 0 1 1 0 12h-2',
  skip: 'M6 6v12l8-6-8-6zm10 0v12',
  info: 'M12 16v-4m0-4h.01M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z',
  gem: 'M6 9 12 3l6 6-6 12L6 9zm0 0h12',
  alert:
    'M12 9v4m0 4h.01M10.3 4.3 2.8 17a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3L13.7 4.3a2 2 0 0 0-3.4 0z',
  trending: 'M4 17l6-6 4 4 6-8M14 7h6v6',
  star: 'M12 3l2.6 5.3 5.9.9-4.3 4.2 1 5.9L12 16.5 6.8 19.3l1-5.9L3.5 9.2l5.9-.9L12 3z',
  bank: 'M3 10l9-6 9 6M5 10v8h14v-8M3 18h18M9 14v4m6-4v4',
  tag: 'M20 12l-8 8-9-9V3h8l9 9zM7.5 7.5h.01',
  check: 'M5 13l4 4L19 7',
  pencil: 'M14 5l5 5M4 20l5-1 11-11-4-4L5 15l-1 5z',
  trash: 'M5 7h14M9 7V5h6v2m-7 0v12h8V7',
  list: 'M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01',
  target: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zm0-5a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm0-4h.01',
}

const d = computed(() => paths[props.name])
</script>

<template>
  <svg
    class="icon"
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    aria-hidden="true"
  >
    <path
      :d="d"
      stroke="currentColor"
      stroke-width="1.75"
      stroke-linecap="round"
      stroke-linejoin="round"
    />
  </svg>
</template>

<style scoped>
.icon {
  display: block;
  flex: 0 0 auto;
}
</style>
