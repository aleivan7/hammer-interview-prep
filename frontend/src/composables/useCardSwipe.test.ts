/**
 * useCardSwipe
 * - left / right / down threshold mapping
 * - cancel vs commit
 * - shared fly-off exit timing for button vs drag paths
 */
import { defineComponent, h } from 'vue'
import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { Bucket } from '../types/bucket'
import { CARD_EXIT_MS, useCardSwipe } from './useCardSwipe'

type SwipeApi = ReturnType<typeof useCardSwipe>

function pointerEvent(
  type: string,
  clientX: number,
  clientY: number,
  extras: Partial<PointerEvent> = {},
): PointerEvent {
  return {
    type,
    button: 0,
    clientX,
    clientY,
    pointerId: 1,
    currentTarget: {
      setPointerCapture: vi.fn(),
    },
    ...extras,
  } as unknown as PointerEvent
}

function mountSwipe(onSwipe: (bucket: Bucket) => void): {
  swipe: SwipeApi
  unmount: () => void
} {
  let swipe!: SwipeApi

  const wrapper = mount(
    defineComponent({
      setup() {
        swipe = useCardSwipe(onSwipe)
        return () => h('div')
      },
    }),
  )

  return {
    swipe,
    unmount: () => wrapper.unmount(),
  }
}

describe('useCardSwipe', () => {
  let onSwipe: ReturnType<typeof vi.fn<(bucket: Bucket) => void>>
  let swipe: SwipeApi
  let unmount: () => void

  beforeEach(() => {
    vi.useFakeTimers()
    vi.stubGlobal(
      'requestAnimationFrame',
      (callback: FrameRequestCallback) =>
        window.setTimeout(() => callback(performance.now()), 0) as unknown as number,
    )
    vi.stubGlobal('cancelAnimationFrame', (id: number) => {
      clearTimeout(id)
    })

    onSwipe = vi.fn()
    ;({ swipe, unmount } = mountSwipe(onSwipe))
  })

  afterEach(() => {
    unmount()
    vi.useRealTimers()
    vi.unstubAllGlobals()
  })

  async function dragTo(dx: number, dy: number, commit = true): Promise<void> {
    swipe.onPointerDown(pointerEvent('pointerdown', 100, 100))
    swipe.onPointerMove(pointerEvent('pointermove', 100 + dx, 100 + dy))
    await vi.advanceTimersByTimeAsync(0)

    if (commit) {
      swipe.onPointerUp()
    } else {
      swipe.onPointerCancel()
    }
  }

  it('ignores drags below the categorization threshold', async () => {
    await dragTo(40, 20)

    expect(onSwipe).not.toHaveBeenCalled()
    expect(swipe.hint.value).toBeNull()
    expect(swipe.cardStyle.value.transform).toContain('translate(0px, 0px)')
  })

  it('maps right to Needs, left to Wants, and down to Savings', async () => {
    await dragTo(120, 10)
    await vi.advanceTimersByTimeAsync(CARD_EXIT_MS + 1)
    expect(onSwipe).toHaveBeenLastCalledWith('need')

    swipe.reset()
    onSwipe.mockClear()

    await dragTo(-120, 10)
    await vi.advanceTimersByTimeAsync(CARD_EXIT_MS + 1)
    expect(onSwipe).toHaveBeenLastCalledWith('want')

    swipe.reset()
    onSwipe.mockClear()

    await dragTo(20, 130)
    await vi.advanceTimersByTimeAsync(CARD_EXIT_MS + 1)
    expect(onSwipe).toHaveBeenLastCalledWith('savings')
  })

  it('prefers vertical Savings when down dominates horizontal movement', async () => {
    await dragTo(80, 140)
    await vi.advanceTimersByTimeAsync(CARD_EXIT_MS + 1)

    expect(onSwipe).toHaveBeenCalledTimes(1)
    expect(onSwipe).toHaveBeenCalledWith('savings')
  })

  it('shows a live hint while dragging past the threshold', async () => {
    swipe.onPointerDown(pointerEvent('pointerdown', 50, 50))
    swipe.onPointerMove(pointerEvent('pointermove', 50 - 80, 50))
    await vi.advanceTimersByTimeAsync(0)

    expect(swipe.dragging.value).toBe(true)
    expect(swipe.hint.value).toBeNull()

    swipe.onPointerMove(pointerEvent('pointermove', 50 - 140, 50))
    await vi.advanceTimersByTimeAsync(0)

    expect(swipe.hint.value).toBe('want')
    expect(swipe.cardStyle.value.transform).toContain('translate(-140px')
  })

  it('resets without categorizing when the pointer is cancelled', async () => {
    await dragTo(150, 0, false)

    expect(onSwipe).not.toHaveBeenCalled()
    expect(swipe.dragging.value).toBe(false)
    expect(swipe.hint.value).toBeNull()
    expect(swipe.cardStyle.value.transform).toContain('translate(0px, 0px)')
  })

  it('blocks upward vertical motion and ignores non-primary buttons', async () => {
    swipe.onPointerDown(pointerEvent('pointerdown', 100, 100, { button: 2 }))
    swipe.onPointerMove(pointerEvent('pointermove', 100, 40))
    await vi.advanceTimersByTimeAsync(0)
    swipe.onPointerUp()

    expect(onSwipe).not.toHaveBeenCalled()
    expect(swipe.dragging.value).toBe(false)

    swipe.onPointerDown(pointerEvent('pointerdown', 100, 100))
    swipe.onPointerMove(pointerEvent('pointermove', 100, 20))
    await vi.advanceTimersByTimeAsync(0)

    expect(swipe.cardStyle.value.transform).toContain('translate(0px, 0px)')
  })

  it('animates button exits with a rest frame before launching', async () => {
    const exit = swipe.beginExit('need')

    expect(swipe.exiting.value).toBe(true)
    expect(swipe.cardStyle.value.transform).toContain('translate(0px, 0px)')

    await vi.advanceTimersByTimeAsync(12)
    expect(swipe.cardStyle.value.transform).toContain('translate(420px')

    await vi.advanceTimersByTimeAsync(CARD_EXIT_MS)
    await exit

    expect(onSwipe).toHaveBeenCalledTimes(1)
    expect(onSwipe).toHaveBeenCalledWith('need')
  })

  it('launches drag exits immediately without an extra rest frame', async () => {
    await dragTo(150, 30)

    expect(swipe.exiting.value).toBe(true)
    expect(swipe.cardStyle.value.transform).toContain('translate(420px')

    await vi.advanceTimersByTimeAsync(CARD_EXIT_MS)
    expect(onSwipe).toHaveBeenCalledWith('need')
  })
})
