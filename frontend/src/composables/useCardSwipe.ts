import { computed, onUnmounted, shallowRef } from 'vue'
import type { Bucket } from '../types/bucket'

interface Point {
  x: number
  y: number
}

const THRESHOLD = 90
export const CARD_EXIT_MS = 140
const EXIT_DISTANCE = 420
const EXIT_FRAME_MS = 12

/**
 * Pointer drag mapping for review cards:
 * left → Wants, right → Needs, down → Savings.
 */
export function useCardSwipe(onSwipe: (bucket: Bucket) => boolean | void) {
  const dragging = shallowRef(false)
  const exiting = shallowRef(false)
  const offsetX = shallowRef(0)
  const offsetY = shallowRef(0)
  const hint = shallowRef<Bucket | null>(null)

  let origin: Point | null = null
  let active = false
  let exitTimer: ReturnType<typeof setTimeout> | null = null
  let moveFrame: number | null = null
  let latestPointer: PointerEvent | null = null
  let exitPromise: Promise<void> | null = null

  function clearExitTimer(): void {
    if (exitTimer != null) {
      clearTimeout(exitTimer)
      exitTimer = null
    }
  }

  function clearMoveFrame(): void {
    if (moveFrame != null) {
      cancelAnimationFrame(moveFrame)
      moveFrame = null
    }
  }

  function flushPendingPointer(): void {
    if (latestPointer && active && origin && !exiting.value) {
      applyPointer(latestPointer)
    }
    latestPointer = null
    clearMoveFrame()
  }

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

  function exitOffset(bucket: Bucket): Point {
    if (bucket === 'want') {
      return { x: -EXIT_DISTANCE, y: Math.max(0, offsetY.value) }
    }

    if (bucket === 'need') {
      return { x: EXIT_DISTANCE, y: Math.max(0, offsetY.value) }
    }

    return { x: offsetX.value * 0.2, y: EXIT_DISTANCE }
  }

  function applyPointer(event: PointerEvent): void {
    if (!active || !origin || exiting.value) {
      return
    }

    offsetX.value = event.clientX - origin.x
    offsetY.value = Math.max(0, event.clientY - origin.y)
    hint.value = bucketFromDelta(offsetX.value, offsetY.value)
  }

  function onPointerDown(event: PointerEvent): void {
    if (event.button !== 0 || exiting.value) {
      return
    }

    clearExitTimer()
    clearMoveFrame()
    latestPointer = null
    exitPromise = null
    active = true
    dragging.value = true
    origin = { x: event.clientX, y: event.clientY }
    ;(event.currentTarget as HTMLElement).setPointerCapture(event.pointerId)
  }

  function onPointerMove(event: PointerEvent): void {
    if (!active || !origin || exiting.value) {
      return
    }

    latestPointer = event
    if (moveFrame != null) {
      return
    }

    moveFrame = requestAnimationFrame(() => {
      moveFrame = null
      if (latestPointer) {
        applyPointer(latestPointer)
      }
    })
  }

  function reset(): void {
    clearExitTimer()
    clearMoveFrame()
    latestPointer = null
    exitPromise = null
    active = false
    dragging.value = false
    exiting.value = false
    origin = null
    offsetX.value = 0
    offsetY.value = 0
    hint.value = null
  }

  function animateExit(bucket: Bucket): Promise<void> {
    if (exitPromise) {
      return exitPromise
    }

    clearExitTimer()
    clearMoveFrame()
    latestPointer = null
    active = false
    dragging.value = false
    origin = null
    exiting.value = true
    hint.value = bucket

    const target = exitOffset(bucket)
    const alreadyDragged = offsetX.value !== 0 || offsetY.value !== 0

    exitPromise = new Promise((resolve) => {
      const finishExit = (): void => {
        exitTimer = null
        resolve()
      }

      const launch = (): void => {
        offsetX.value = target.x
        offsetY.value = target.y
        exitTimer = setTimeout(finishExit, CARD_EXIT_MS)
      }

      if (alreadyDragged) {
        launch()
      } else {
        // Give the browser one frame at the rest position so the CSS transition runs.
        exitTimer = setTimeout(launch, EXIT_FRAME_MS)
      }
    })

    return exitPromise
  }

  async function beginExit(bucket: Bucket): Promise<void> {
    await animateExit(bucket)
    const accepted = onSwipe(bucket)
    if (accepted === false) {
      reset()
    }
  }

  function finish(commit: boolean): void {
    if (!active || exiting.value) {
      return
    }

    if (commit) {
      flushPendingPointer()
    } else {
      latestPointer = null
      clearMoveFrame()
    }

    const bucket = commit ? bucketFromDelta(offsetX.value, offsetY.value) : null
    active = false
    dragging.value = false
    origin = null

    if (!bucket) {
      offsetX.value = 0
      offsetY.value = 0
      hint.value = null
      return
    }

    void beginExit(bucket)
  }

  function onPointerUp(): void {
    finish(true)
  }

  function onPointerCancel(): void {
    finish(false)
  }

  const cardStyle = computed(() => ({
    transform: `translate(${offsetX.value}px, ${offsetY.value}px) rotate(${offsetX.value / 28}deg)`,
    transition: dragging.value ? 'none' : `transform ${CARD_EXIT_MS}ms ease`,
    willChange: dragging.value || exiting.value ? 'transform' : 'auto',
    opacity: exiting.value ? 0.92 : 1,
  }))

  onUnmounted(() => {
    clearExitTimer()
    clearMoveFrame()
    latestPointer = null
    active = false
  })

  return {
    dragging,
    exiting,
    hint,
    cardStyle,
    onPointerDown,
    onPointerMove,
    onPointerUp,
    onPointerCancel,
    beginExit,
    reset,
  }
}
