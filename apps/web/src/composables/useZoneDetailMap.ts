import { onUnmounted, ref, type Ref } from 'vue'
import type * as Leaflet from 'leaflet'
import type { Map as LeafletMap } from 'leaflet'
import { isAbortError } from '@/composables/useAbortableRequest'

export function useZoneDetailMap(mapElement: Ref<HTMLElement | null>) {
  const mapLoading = ref(false)
  const mapError = ref('')
  let map: LeafletMap | null = null
  let leafletLoader: Promise<typeof Leaflet> | null = null

  function destroyMap() {
    if (map) {
      map.remove()
      map = null
    }
  }

  function loadLeaflet() {
    leafletLoader ??= import('leaflet').then((module) => {
      const moduleWithDefault = module as typeof module & { default?: typeof Leaflet }

      return moduleWithDefault.default ?? module
    })

    return leafletLoader
  }

  async function renderMap(
    zone: { id: number; latitude: number; longitude: number },
    isCurrentRequest: () => boolean,
  ) {
    if (!mapElement.value) {
      return
    }

    const targetElement = mapElement.value
    mapLoading.value = true
    mapError.value = ''

    try {
      const leaflet = await loadLeaflet()

      if (!isCurrentRequest() || mapElement.value !== targetElement) {
        return
      }

      destroyMap()
      map = leaflet.map(targetElement).setView([zone.latitude, zone.longitude], 15)

      leaflet
        .tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          attribution: '&copy; OpenStreetMap contributors',
        })
        .addTo(map)
      leaflet.marker([zone.latitude, zone.longitude]).addTo(map)

      requestAnimationFrame(() => {
        map?.invalidateSize()
      })
    } catch (err) {
      if (isCurrentRequest() && !isAbortError(err)) {
        mapError.value = 'Interactive map unavailable'
      }
    } finally {
      if (isCurrentRequest()) {
        mapLoading.value = false
      }
    }
  }

  function resetMapState() {
    mapError.value = ''
    mapLoading.value = false
    destroyMap()
  }

  onUnmounted(() => {
    destroyMap()
  })

  return {
    mapLoading,
    mapError,
    renderMap,
    resetMapState,
    destroyMap,
  }
}
