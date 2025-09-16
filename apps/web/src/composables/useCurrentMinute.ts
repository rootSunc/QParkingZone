import { onMounted, onUnmounted, ref } from 'vue'

export function useCurrentMinute() {
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
