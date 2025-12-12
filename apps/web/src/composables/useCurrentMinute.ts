import {
  inject,
  onMounted,
  onUnmounted,
  provide,
  ref,
  type InjectionKey,
  type Ref,
} from 'vue'

export const currentMinuteKey: InjectionKey<Ref<Date>> = Symbol('currentMinute')

function createCurrentMinuteClock(): Ref<Date> {
  const now = ref(new Date())
  let timer: number | null = null

  function clearTimer() {
    if (timer !== null) {
      window.clearTimeout(timer)
      timer = null
    }
  }

  function scheduleTick() {
    clearTimer()

    const delay = Math.max(250, 60000 - (Date.now() % 60000))

    timer = window.setTimeout(() => {
      now.value = new Date()
      scheduleTick()
    }, delay)
  }

  onMounted(() => {
    now.value = new Date()
    scheduleTick()
  })

  onUnmounted(() => {
    clearTimer()
  })

  return now
}

/** Provide a single shared minute clock for the app shell. */
export function provideCurrentMinute(): Ref<Date> {
  const now = createCurrentMinuteClock()
  provide(currentMinuteKey, now)
  return now
}

/**
 * Prefer the app-provided clock when available so list cards share one timer.
 * Falls back to a local clock for isolated usage (e.g. tests without provider).
 */
export function useCurrentMinute(): Ref<Date> {
  const shared = inject(currentMinuteKey, null)

  if (shared !== null) {
    return shared
  }

  return createCurrentMinuteClock()
}
