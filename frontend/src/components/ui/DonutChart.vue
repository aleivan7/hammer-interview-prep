<script setup lang="ts">
import { computed } from 'vue'

export interface DonutSegment {
  value: number
  color: string
  label?: string
}

const props = withDefaults(
  defineProps<{
    segments: DonutSegment[]
    centerValue: string
    centerLabel: string
    size?: number
    thickness?: number
  }>(),
  { size: 150, thickness: 14 },
)

const radius = computed(() => (props.size - props.thickness) / 2)
const circumference = computed(() => 2 * Math.PI * radius.value)
const center = computed(() => props.size / 2)

const arcs = computed(() => {
  const total = props.segments.reduce((sum, segment) => sum + Math.max(0, segment.value), 0)
  if (total <= 0) {
    return []
  }

  let offset = 0
  return props.segments
    .filter((segment) => segment.value > 0)
    .map((segment) => {
      const length = (segment.value / total) * circumference.value
      const arc = {
        color: segment.color,
        dasharray: `${length} ${circumference.value - length}`,
        dashoffset: -offset,
      }
      offset += length
      return arc
    })
})

const ariaLabel = computed(() => {
  const parts = props.segments
    .filter((segment) => segment.value > 0)
    .map((segment) => `${segment.label ?? 'segment'} ${segment.value}`)
  return `Chart: ${props.centerValue} ${props.centerLabel}. ${parts.join(', ')}`
})

const viewBox = computed(() => `0 0 ${props.size} ${props.size}`)
</script>

<template>
  <div class="donut" :style="{ width: `${size}px`, height: `${size}px` }" role="img" :aria-label="ariaLabel">
    <svg :width="size" :height="size" :viewBox="viewBox">
      <circle
        :cx="center"
        :cy="center"
        :r="radius"
        fill="none"
        stroke="rgba(255,255,255,0.06)"
        :stroke-width="thickness"
      />
      <circle
        v-for="(arc, index) in arcs"
        :key="index"
        :cx="center"
        :cy="center"
        :r="radius"
        fill="none"
        :stroke="arc.color"
        :stroke-width="thickness"
        :stroke-dasharray="arc.dasharray"
        :stroke-dashoffset="arc.dashoffset"
        stroke-linecap="butt"
        transform-origin="center"
        :style="{ transform: 'rotate(-90deg)' }"
      />
    </svg>
    <div class="center">
      <p class="value">{{ centerValue }}</p>
      <p class="label">{{ centerLabel }}</p>
    </div>
  </div>
</template>

<style scoped>
.donut {
  position: relative;
  display: grid;
  place-items: center;
}

.center {
  position: absolute;
  inset: 0;
  display: grid;
  place-content: center;
  text-align: center;
  pointer-events: none;
}

.value {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.label {
  margin: 0.1rem 0 0;
  color: var(--text-muted);
  font-size: 0.7rem;
  max-width: 5.5rem;
  line-height: 1.25;
}
</style>
