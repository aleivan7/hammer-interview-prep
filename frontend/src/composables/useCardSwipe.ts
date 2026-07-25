import { computed, onUnmounted, shallowRef } from 'vue'
import type { Bucket } from '../types/bucket'

interface Point {
  x: number
  y: number
}

const THRESHOLD = 90

/**
 * Pointer drag mapping for review cards:
 * left → Wants, right → Needs, down → Savings.
 */
export function useCardSwipe(onSwipe: (bucket: Bucket) => void) {
  const dragging = shallowRef(false)
  const offsetX = shallowRef(0)
  const offsetY = shallowRef(0)
  const hint = shallowRef<Bucket | null>(null)

  let origin: Point | null = null
  let active = false

  function bucketFromDelta(dx: number, dy: number): Bucket | null {
    if (Math.abs(dx) < THRESHOLD && Math.abs(dy) < THRESHOLD) {
      return null
    }

    if (Math.abs(dy) > Math.abs(dx) && dy > 0) {
      return 'savings'
    }

    if (dx < 0) {
      return 'want'
    }

    if (dx > 0) {
      return 'need'
    }

    return null
  }

  function onPointerDown(event: PointerEvent): void {
    if (event.button !== 0) {
      return
    }

    active = true
    dragging.value = true
    origin = { x: event.clientX, y: event.clientY }
    ;(event.currentTarget as HTMLElement).setPointerCapture(event.pointerId)
  }

  function onPointerMove(event: PointerEvent): void {
    if (!active || !origin) {
      return
    }

    offsetX.value = event.clientX - origin.x
    offsetY.value = Math.max(0, event.clientY - origin.y)
    hint.value = bucketFromDelta(offsetX.value, offsetY.value)
  }

  function finish(commit: boolean): void {
    if (!active) {
      return
    }

    const bucket = commit ? bucketFromDelta(offsetX.value, offsetY.value) : null
    active = false
    dragging.value = false
    origin = null
    offsetX.value = 0
    offsetY.value = 0
    hint.value = null

    if (bucket) {
      onSwipe(bucket)
    }
  }

  function onPointerUp(): void {
    finish(true)
  }

  function onPointerCancel(): void {
    finish(false)
  }

  const cardStyle = computed(() => ({
    transform: `translate(${offsetX.value}px, ${offsetY.value}px) rotate(${offsetX.value / 28}deg)`,
    transition: dragging.value ? 'none' : 'transform 180ms ease',
  }))

  onUnmounted(() => {
    active = false
  })

  return {
    dragging,
    hint,
    cardStyle,
    onPointerDown,
    onPointerMove,
    onPointerUp,
    onPointerCancel,
  }
}
