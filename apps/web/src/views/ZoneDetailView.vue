<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { fetchZone, type ZoneDetail } from '../api/zones'
import { useCurrentMinute } from '@/composables/useCurrentMinute'
import { getCityLabel } from '@/config/cities'
import { useCitySelection } from '@/composables/useCitySelection'
import { updateZoneCatalogQuery } from '@/composables/useZoneCatalogRoute'
import { getZoneAvailability } from '@/utils/zoneAvailability'
import 'leaflet/dist/leaflet.css'

const props = defineProps<{ id: string }>()
const route = useRoute()
const router = useRouter()
const zone = ref<ZoneDetail | null>(null)
const loading = ref(false)
const error = ref('')
const mapLoading = ref(false)
const mapError = ref('')
const mapElement = ref<HTMLElement | null>(null)
const { selectedCity, selectedCityLabel } = useCitySelection(() => route.query)
const now = useCurrentMinute()
let map: any | null = null
let activeRequestId = 0
let activeController: AbortController | null = null
let leafletLoader: Promise<any> | null = null

const mapUrl = computed(() => {
  if (!zone.value) {
    return '#'
  }

  return `https://www.openstreetmap.org/?mlat=${zone.value.latitude}&mlon=${zone.value.longitude}#map=16/${zone.value.latitude}/${zone.value.longitude}`
})

const zoneCityLabel = computed(() => {
  if (!zone.value) {
    return selectedCityLabel.value
  }

  return getCityLabel(zone.value.city)
})

const backToZonesRoute = computed(() => {
  return {
    name: 'zones',
    query: updateZoneCatalogQuery(route.query, {
      city: zone.value?.city ?? selectedCity.value,
    }),
  }
})

const availability = computed(() => {
  if (!zone.value) {
    return null
  }

  return getZoneAvailability(zone.value.status, zone.value.openingHours, now.value)
})

const availabilityClass = computed(() => {
  return availability.value?.state ?? 'closed'
})

function destroyMap() {
  if (map) {
    map.remove()
    map = null
  }
}

function isAbortError(error: unknown): boolean {
  return error instanceof Error && error.name === 'AbortError'
}

function loadLeaflet() {
  leafletLoader ??= import('leaflet').then((module) => module.default)

  return leafletLoader
}

async function renderMap(requestId: number) {
  if (!zone.value || !mapElement.value) {
    return
  }

  const currentZone = zone.value
  const targetElement = mapElement.value
  mapLoading.value = true
  mapError.value = ''

  try {
    const leaflet = await loadLeaflet()

    if (requestId !== activeRequestId || zone.value?.id !== currentZone.id || mapElement.value !== targetElement) {
      return
    }

    destroyMap()
    map = leaflet.map(targetElement).setView([currentZone.latitude, currentZone.longitude], 15)

    leaflet
      .tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
      })
      .addTo(map)
    leaflet.marker([currentZone.latitude, currentZone.longitude]).addTo(map)

    requestAnimationFrame(() => {
      map?.invalidateSize()
    })
  } catch (err) {
    if (requestId === activeRequestId && !isAbortError(err)) {
      mapError.value = 'Interactive map unavailable'
    }
  } finally {
    if (requestId === activeRequestId) {
      mapLoading.value = false
    }
  }
}

async function loadZone(id: string) {
  const requestId = ++activeRequestId
  activeController?.abort()
  const controller = new AbortController()
  activeController = controller
  loading.value = true
  error.value = ''
  mapError.value = ''
  mapLoading.value = false
  zone.value = null
  destroyMap()

  try {
    const data = await fetchZone(id, controller.signal)
    if (requestId !== activeRequestId) {
      return
    }

    zone.value = data
    loading.value = false

    if (selectedCity.value !== data.city) {
      await router.replace({
        query: updateZoneCatalogQuery(route.query, {
          city: data.city,
        }),
      })
    }

    await nextTick()

    if (requestId !== activeRequestId) {
      return
    }

    await renderMap(requestId)
  } catch (err) {
    if (requestId !== activeRequestId || isAbortError(err)) {
      return
    }

    error.value = err instanceof Error ? err.message : 'Failed to load zone'
    loading.value = false
    mapLoading.value = false
  } finally {
    if (requestId === activeRequestId && activeController === controller) {
      activeController = null
    }
  }
}

watch(() => props.id, loadZone, { immediate: true })

onUnmounted(() => {
  activeRequestId += 1
  activeController?.abort()
  activeController = null
  destroyMap()
})
</script>

<template>
  <section class="page">
    <RouterLink :to="backToZonesRoute" class="back-link">Back to {{ zoneCityLabel }} zones</RouterLink>

    <div v-if="loading" class="state">Loading zone details…</div>

    <div v-else-if="error" class="state error">{{ error }}</div>

    <article v-else-if="zone" class="detail-card">
      <header class="hero">
        <div class="hero-copy">
          <div class="eyebrow">Parking zone {{ zone.id }}</div>
          <h1>{{ zone.name }}</h1>
          <p class="description">{{ zone.description }}</p>

          <div class="hero-actions">
            <a :href="mapUrl" target="_blank" rel="noreferrer" class="hero-action">Open on map</a>

            <div class="badges">
              <span class="badge city">{{ zoneCityLabel }}</span>
              <span class="badge type">{{ zone.type }}</span>
              <span class="badge" :class="zone.status === 'active' ? 'active' : 'inactive'">
                {{ zone.status }}
              </span>
              <span v-if="availability" class="badge badge-availability" :class="availabilityClass">
                {{ availability.badge }}
              </span>
            </div>
          </div>
        </div>

        <div class="hero-stats">
          <div class="stat stat-accent">
            <div class="label">Hourly rate</div>
            <div class="value">€{{ zone.hourlyRateEur.toFixed(2) }}/hour</div>
          </div>

          <div class="stat">
            <div class="label">Max capacity</div>
            <div class="value">{{ zone.maxCapacity }}</div>
            <div class="stat-note">parking spaces</div>
          </div>

          <div class="stat">
            <div class="label">Coordinates</div>
            <div class="value value-small">
              {{ zone.latitude.toFixed(4) }}, {{ zone.longitude.toFixed(4) }}
            </div>
          </div>
        </div>
      </header>

      <section class="panel-grid">
        <div class="panel panel-dark">
          <p class="panel-kicker">Plan your stop</p>
          <h2>Opening Hours</h2>

          <div v-if="availability" class="availability-summary" :class="availabilityClass">
            <strong>{{ availability.badge }}</strong>
            <span>{{ availability.detail }}</span>
          </div>

          <div class="detail-row">
            <span class="detail-label">Weekdays</span>
            <span>{{ zone.openingHours.weekdays }}</span>
          </div>

          <div class="detail-row">
            <span class="detail-label">Weekends</span>
            <span>{{ zone.openingHours.weekends }}</span>
          </div>
        </div>

        <div class="panel">
          <p class="panel-kicker">Included</p>
          <h2>Amenities</h2>

          <div class="amenities">
            <span v-for="amenity in zone.amenities" :key="amenity" class="amenity-tag">
              {{ amenity }}
            </span>
          </div>
        </div>
      </section>

      <section class="location-panel">
        <div class="location-copy">
          <div>
            <p class="panel-kicker">Location</p>
            <h2>Check the exact zone before you park.</h2>
            <p class="location-text">
              Preview the embedded map, confirm the coordinates, and open OpenStreetMap when you
              need the full street context.
            </p>
          </div>

          <div class="location-points">
            <div class="location-point">
              <span class="point-label">Latitude</span>
              <span class="point-value">{{ zone.latitude.toFixed(4) }}</span>
            </div>

            <div class="location-point">
              <span class="point-label">Longitude</span>
              <span class="point-value">{{ zone.longitude.toFixed(4) }}</span>
            </div>

            <div class="location-point">
              <span class="point-label">Capacity</span>
              <span class="point-value">{{ zone.maxCapacity }} spaces</span>
            </div>
          </div>

          <a :href="mapUrl" target="_blank" rel="noreferrer" class="map-link">Launch map</a>
        </div>

        <div class="map-shell">
          <div ref="mapElement" class="map"></div>
          <div v-if="mapLoading || mapError" class="map-overlay">
            {{ mapError || 'Loading interactive map…' }}
          </div>
        </div>
      </section>
    </article>
  </section>
</template>

<style scoped>
.page {
  display: grid;
  gap: 20px;
}

.back-link {
  align-self: flex-start;
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  padding: 0 16px;
  border-radius: 999px;
  background: rgba(18, 18, 18, 0.08);
  color: var(--text-muted);
  font-size: 14px;
  font-weight: 800;
  transition:
    transform 0.18s ease,
    background 0.18s ease;
}

.back-link:hover {
  transform: translateY(-1px);
  background: rgba(18, 18, 18, 0.12);
}

.state {
  padding: 48px 20px;
  border-radius: var(--radius-lg);
  background: rgba(255, 255, 255, 0.8);
  color: var(--text-muted);
  text-align: center;
  font-weight: 700;
  box-shadow: var(--shadow-soft);
}

.state.error {
  background: var(--danger-soft);
  color: var(--danger-text);
}

.detail-card {
  display: grid;
  gap: 24px;
}

.hero {
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) minmax(280px, 0.8fr);
  gap: 22px;
  padding: clamp(28px, 4vw, 42px);
  border-radius: var(--radius-xl);
  background: var(--surface-dark);
  color: white;
  box-shadow: var(--shadow-strong);
}

.hero-copy {
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.eyebrow {
  margin-bottom: 10px;
  color: rgba(255, 255, 255, 0.62);
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.16em;
  text-transform: uppercase;
}

.hero h1 {
  margin: 0;
  font-size: clamp(2.8rem, 5vw, 4.8rem);
  line-height: 0.96;
  letter-spacing: -0.06em;
}

.description {
  margin: 18px 0 0;
  max-width: 56ch;
  color: rgba(255, 255, 255, 0.74);
  font-size: 17px;
}

.hero-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 14px;
  margin-top: 26px;
}

.hero-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 50px;
  padding: 0 20px;
  border-radius: 999px;
  background: var(--accent);
  color: var(--surface-dark);
  font-weight: 800;
  transition:
    transform 0.18s ease,
    background 0.18s ease;
}

.hero-action:hover {
  transform: translateY(-2px);
  background: var(--accent-strong);
}

.badges {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.badge {
  display: inline-flex;
  align-items: center;
  min-height: 38px;
  padding: 0 12px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 800;
  text-transform: capitalize;
}

.badge.city {
  background: rgba(138, 242, 90, 0.18);
  color: #daf5c7;
}

.badge.type {
  background: rgba(255, 255, 255, 0.12);
  color: rgba(255, 255, 255, 0.78);
}

.badge.active {
  background: var(--accent);
  color: var(--surface-dark);
}

.badge.inactive {
  background: rgba(220, 38, 38, 0.18);
  color: #fecaca;
}

.badge-availability.open {
  background: rgba(138, 242, 90, 0.18);
  color: #daf5c7;
}

.badge-availability.closed {
  background: rgba(255, 255, 255, 0.14);
  color: rgba(255, 255, 255, 0.82);
}

.badge-availability.inactive {
  background: rgba(220, 38, 38, 0.18);
  color: #fecaca;
}

.hero-stats {
  display: grid;
  gap: 16px;
  align-content: start;
}

.stat {
  padding: 22px;
  border-radius: 24px;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.08);
}

.stat-accent {
  background: var(--accent);
  color: var(--surface-dark);
  border-color: transparent;
}

.label {
  margin-bottom: 8px;
  color: inherit;
  opacity: 0.74;
  font-size: 13px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.value {
  font-size: clamp(28px, 2.5vw, 36px);
  font-weight: 700;
  line-height: 1;
  letter-spacing: -0.05em;
}

.value-small {
  font-size: 16px;
  font-weight: 700;
  line-height: 1.4;
}

.stat-note {
  margin-top: 10px;
  color: rgba(255, 255, 255, 0.62);
  font-size: 13px;
  font-weight: 700;
}

.stat-accent .stat-note {
  color: rgba(18, 18, 18, 0.68);
}

.panel-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 18px;
}

.panel {
  padding: 26px;
  border-radius: var(--radius-lg);
  background: white;
  border: 1px solid var(--line-soft);
  box-shadow: var(--shadow-soft);
}

.panel-dark {
  background: var(--surface-dark-soft);
  color: white;
  border-color: rgba(255, 255, 255, 0.06);
}

.panel-kicker {
  margin: 0 0 10px;
  color: var(--text-subtle);
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.panel-dark .panel-kicker,
.panel-dark .detail-label {
  color: rgba(255, 255, 255, 0.6);
}

.panel h2 {
  margin: 0 0 16px;
  font-size: 28px;
  line-height: 1;
  letter-spacing: -0.04em;
}

.availability-summary {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 12px;
  align-items: center;
  margin-bottom: 18px;
  padding: 14px 16px;
  border-radius: 18px;
  font-size: 14px;
  font-weight: 700;
}

.availability-summary.open {
  background: rgba(138, 242, 90, 0.14);
  color: #daf5c7;
}

.availability-summary.closed {
  background: rgba(255, 255, 255, 0.08);
  color: rgba(255, 255, 255, 0.84);
}

.availability-summary.inactive {
  background: rgba(220, 38, 38, 0.14);
  color: #fecaca;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  padding: 14px 0;
  border-top: 1px solid rgba(18, 18, 18, 0.08);
}

.detail-row:first-of-type {
  padding-top: 0;
  border-top: none;
}

.detail-label {
  color: var(--text-muted);
  font-weight: 700;
}

.amenities {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.amenity-tag {
  padding: 8px 12px;
  border-radius: 999px;
  background: var(--accent-soft);
  color: #315b1f;
  font-size: 13px;
  font-weight: 800;
}

.location-panel {
  display: grid;
  grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
  gap: 18px;
  padding: 18px;
  border-radius: var(--radius-xl);
  background: rgba(255, 255, 255, 0.78);
  border: 1px solid var(--line-soft);
  box-shadow: var(--shadow-soft);
}

.location-copy {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 20px;
  padding: 10px;
}

.location-copy h2 {
  margin: 0;
  font-size: clamp(2rem, 3vw, 3.4rem);
  line-height: 0.95;
  letter-spacing: -0.05em;
}

.location-text {
  margin: 16px 0 0;
  color: var(--text-muted);
  font-size: 16px;
}

.location-points {
  display: grid;
  gap: 10px;
}

.location-point {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  padding: 14px 16px;
  border-radius: 20px;
  background: white;
  border: 1px solid rgba(18, 18, 18, 0.08);
}

.point-label {
  color: var(--text-subtle);
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.point-value {
  font-weight: 800;
}

.map-shell {
  position: relative;
  min-height: 100%;
  padding: 6px;
  border-radius: 28px;
  background: white;
  border: 1px solid rgba(18, 18, 18, 0.08);
}

.map {
  width: 100%;
  height: 100%;
  min-height: 360px;
  border-radius: 22px;
  overflow: hidden;
  border: 1px solid rgba(18, 18, 18, 0.08);
}

.map-overlay {
  position: absolute;
  inset: 24px 24px auto 24px;
  display: inline-flex;
  justify-content: center;
  align-items: center;
  min-height: 48px;
  padding: 0 18px;
  border-radius: 999px;
  background: rgba(18, 18, 18, 0.72);
  color: white;
  font-size: 13px;
  font-weight: 800;
  backdrop-filter: blur(10px);
}

.map-link {
  align-self: flex-start;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 50px;
  padding: 0 18px;
  border-radius: 999px;
  background: var(--surface-dark);
  color: white;
  font-weight: 800;
}

.map-link:hover {
  background: #272727;
}

@media (max-width: 1100px) {
  .hero,
  .location-panel {
    grid-template-columns: 1fr;
  }

  .location-copy {
    padding: 8px;
  }
}

@media (max-width: 780px) {
  .panel-grid {
    grid-template-columns: 1fr;
  }

  .detail-row,
  .location-point {
    flex-direction: column;
    gap: 6px;
  }
}

@media (max-width: 640px) {
  .hero,
  .panel,
  .location-panel {
    border-radius: 28px;
  }

  .hero,
  .panel {
    padding: 22px;
  }

  .location-panel {
    padding: 14px;
  }
}
</style>
