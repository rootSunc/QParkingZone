import { onUnmounted } from 'vue'

export interface AbortableRequest {
  controller: AbortController
  isCurrent: () => boolean
  finish: () => void
}

export function isAbortError(error: unknown): boolean {
  return error instanceof Error && error.name === 'AbortError'
}

export function useAbortableRequest() {
  let activeRequestId = 0
  let activeController: AbortController | null = null

  function beginRequest(): AbortableRequest {
    const requestId = ++activeRequestId
    activeController?.abort()

    const controller = new AbortController()
    activeController = controller

    return {
      controller,
      isCurrent: () => requestId === activeRequestId,
      finish: () => {
        if (requestId === activeRequestId && activeController === controller) {
          activeController = null
        }
      },
    }
  }

  function cancelActiveRequest() {
    activeRequestId += 1
    activeController?.abort()
    activeController = null
  }

  onUnmounted(cancelActiveRequest)

  return {
    beginRequest,
    cancelActiveRequest,
  }
}
