import { onUnmounted, ref } from 'vue'

export interface BrowserCoordinates {
  latitude: number
  longitude: number
}

export function useBrowserGeolocation() {
  const locating = ref(false)
  const locationError = ref('')
  let disposed = false

  function clearLocationError() {
    locationError.value = ''
  }

  function locateUser(onLocated: (coordinates: BrowserCoordinates) => void) {
    if (typeof navigator === 'undefined' || !('geolocation' in navigator)) {
      locationError.value = 'Geolocation is unavailable in this browser.'
      return
    }

    locating.value = true
    locationError.value = ''

    navigator.geolocation.getCurrentPosition(
      (position) => {
        if (disposed) {
          return
        }

        locating.value = false
        onLocated({
          latitude: position.coords.latitude,
          longitude: position.coords.longitude,
        })
      },
      () => {
        if (disposed) {
          return
        }

        locating.value = false
        locationError.value = 'Location access was denied.'
      },
      {
        enableHighAccuracy: true,
        timeout: 8000,
        maximumAge: 300000,
      },
    )
  }

  onUnmounted(() => {
    disposed = true
  })

  return {
    locating,
    locationError,
    clearLocationError,
    locateUser,
  }
}
