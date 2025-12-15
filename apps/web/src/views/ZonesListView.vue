<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { fetchZoneFacets, fetchZones, type ZoneFacets, type ZonesPage } from '@/api/zones'
import { isAbortError, useAbortableRequest } from '@/composables/useAbortableRequest'
import { useBrowserGeolocation } from '@/composables/useBrowserGeolocation'
import { useCitySelection } from '@/composables/useCitySelection'
import {
  defaultZonePageSize,
  parseZoneCatalogQuery,
  updateZoneCatalogQuery,
  type ZoneCatalogQueryState,
  type ZoneSort,
} from '@/composables/useZoneCatalogRoute'
import ZoneCard from '@/components/ZoneCard.vue'
import ZonesCatalogFilters from '@/components/ZonesCatalogFilters.vue'
import ZonesCatalogHero from '@/components/ZonesCatalogHero.vue'

const SEARCH_DEBOUNCE_MS = 300

const route = useRoute()
const router = useRouter()
const pageData = ref<ZonesPage>({
  items: [],
  total: 0,
  page: 1,
  limit: defaultZonePageSize,
})
const facets = ref<ZoneFacets>({
  city: null,
  types: [],
  statuses: [],
  amenities: [],
})
const loading = ref(false)
const error = ref('')
const facetsError = ref('')
const { selectedCityLabel } = useCitySelection(() => route.query)
const catalogState = computed(() => parseZoneCatalogQuery(route.query))
const { beginRequest: beginZonesRequest } = useAbortableRequest()
const { beginRequest: beginFacetsRequest } = useAbortableRequest()
const { locating, locationError, clearLocationError, locateUser: requestUserLocation } = useBrowserGeolocation()

const zones = computed(() => {
  return pageData.value.items
})

const availableTypes = computed(() => {
  const values = facets.value.types.map((facet) => facet.value)

  return values.length > 0 ? values : [...new Set(zones.value.map((zone) => zone.type))]
})

const searchQuery = ref(catalogState.value.q)
let searchDebounceTimer: number | null = null

function clearSearchDebounce() {
  if (searchDebounceTimer !== null) {
    window.clearTimeout(searchDebounceTimer)
    searchDebounceTimer = null
  }
}

watch(
  () => catalogState.value.q,
  (q) => {
    if (searchQuery.value !== q) {
      searchQuery.value = q
    }
  },
)

watch(searchQuery, (value) => {
  if (value === catalogState.value.q) {
    return
  }

  clearSearchDebounce()
  searchDebounceTimer = window.setTimeout(() => {
    searchDebounceTimer = null
    replaceCatalogQuery({
      q: value,
      page: 1,
    })
  }, SEARCH_DEBOUNCE_MS)
})

onUnmounted(() => {
  clearSearchDebounce()
})

const sortBy = computed<ZoneSort>({
  get() {
    return catalogState.value.sort
  },
  set(value) {
    replaceCatalogQuery({
      sort: value,
      page: 1,
    })
  },
})

const activeTypeFilter = computed(() => {
  return catalogState.value.type
})

const openNowOnly = computed(() => {
  return catalogState.value.openNow
})

const radius = computed({
  get() {
    return catalogState.value.radius
  },
  set(value) {
    replaceCatalogQuery({
      radius: value,
      page: 1,
    })
  },
})

const activeAmenities = computed(() => {
  return catalogState.value.amenities
})

const availableAmenities = computed(() => {
  return facets.value.amenities.map((facet) => facet.value)
})

const hasLocation = computed(() => {
  return catalogState.value.lat !== null && catalogState.value.lng !== null
})



const totalPages = computed(() => {
  if (pageData.value.total === 0) {
    return 1
  }

  return Math.ceil(pageData.value.total / pageData.value.limit)
})

const currentRange = computed(() => {
  if (pageData.value.total === 0) {
    return 'No results'
  }

  const start = (pageData.value.page - 1) * pageData.value.limit + 1
  const end = start + zones.value.length - 1

  return `Showing ${start}-${end} of ${pageData.value.total}`
})

const averageRate = computed(() => {
  if (!zones.value.length) {
    return 0
  }

  const total = zones.value.reduce((sum, zone) => sum + zone.hourlyRateEur, 0)
  return total / zones.value.length
})

const previewZone = computed(() => {
  return zones.value[0] ?? null
})

function toggleTypeFilter(nextType: string) {
  replaceCatalogQuery({
    type: activeTypeFilter.value === nextType ? null : nextType,
    page: 1,
  })
}

function clearTypeFilter() {
  replaceCatalogQuery({
    type: null,
    page: 1,
  })
}

function toggleOpenNow() {
  replaceCatalogQuery({
    openNow: !openNowOnly.value,
    page: 1,
  })
}

function toggleAmenity(amenity: string) {
  const current = new Set(activeAmenities.value)
  if (current.has(amenity)) {
    current.delete(amenity)
  } else {
    current.add(amenity)
  }
  
  replaceCatalogQuery({
    amenities: Array.from(current),
    page: 1,
  })
}

function clearAmenities() {
  replaceCatalogQuery({
    amenities: [],
    page: 1,
  })
}

function scrollToZones() {
  if (typeof document === 'undefined') {
    return
  }

  document.getElementById('zones-grid')?.scrollIntoView({
    behavior: 'smooth',
    block: 'start',
  })
}

function replaceCatalogQuery(patch: Partial<ZoneCatalogQueryState>) {
  router.replace({
    query: updateZoneCatalogQuery(route.query, patch),
  })
}

function clearLocation() {
  replaceCatalogQuery({
    lat: null,
    lng: null,
    sort: catalogState.value.sort === 'distance_asc' ? 'name' : catalogState.value.sort,
    page: 1,
  })
}

function applyUserLocation(latitude: number, longitude: number) {
  clearLocationError()
  replaceCatalogQuery({
    lat: latitude,
    lng: longitude,
    sort: 'distance_asc',
    page: 1,
  })
}

function locateUser() {
  requestUserLocation(({ latitude, longitude }) => applyUserLocation(latitude, longitude))
}

function goToPage(nextPage: number) {
  if (nextPage < 1 || nextPage > totalPages.value || nextPage === pageData.value.page) {
    return
  }

  router.push({
    query: updateZoneCatalogQuery(route.query, {
      page: nextPage,
    }),
  })
  scrollToZones()
}

async function loadZones(state: ZoneCatalogQueryState) {
  const request = beginZonesRequest()
  loading.value = true
  error.value = ''

  try {
    const data = await fetchZones(state, request.controller.signal)

    if (!request.isCurrent()) {
      return
    }

    const responsePageCount = Math.max(1, Math.ceil(data.total / data.limit))

    if (data.total > 0 && data.items.length === 0 && data.page > responsePageCount) {
      router.replace({
        query: updateZoneCatalogQuery(route.query, {
          page: responsePageCount,
        }),
      })

      return
    }

    pageData.value = data
  } catch (err) {
    if (!request.isCurrent() || isAbortError(err)) {
      return
    }

    error.value = err instanceof Error ? err.message : 'Failed to load zones'
  } finally {
    if (request.isCurrent()) {
      loading.value = false
      request.finish()
    }
  }
}

async function loadFacets(city: ZoneCatalogQueryState['city']) {
  const request = beginFacetsRequest()

  try {
    const data = await fetchZoneFacets(city, request.controller.signal)

    if (!request.isCurrent()) {
      return
    }

    facets.value = data
    facetsError.value = ''
  } catch (err) {
    if (!request.isCurrent() || isAbortError(err)) {
      return
    }

    facets.value = {
      city,
      types: [],
      statuses: [],
      amenities: [],
    }
    facetsError.value = err instanceof Error ? err.message : 'Failed to load filters'
  } finally {
    if (request.isCurrent()) {
      request.finish()
    }
  }
}

watch(() => catalogState.value.city, loadFacets, { immediate: true })
watch(catalogState, loadZones, { immediate: true })
</script>

<template>
  <section class="page">
    <ZonesCatalogHero
      :selected-city-label="selectedCityLabel"
      :total="pageData.total"
      :shown-count="zones.length"
      :average-rate="averageRate"
      :preview-zone="previewZone"
      :has-location="hasLocation"
      @explore="scrollToZones"
    />

    <section id="zones-grid" class="catalog-shell">
      <ZonesCatalogFilters
        v-model:search-query="searchQuery"
        v-model:sort-by="sortBy"
        v-model:radius="radius"
        :selected-city-label="selectedCityLabel"
        :total="pageData.total"
        :has-location="hasLocation"
        :locating="locating"
        :location-error="locationError"
        :open-now-only="openNowOnly"
        :active-type-filter="activeTypeFilter"
        :active-amenities="activeAmenities"
        :available-types="availableTypes"
        :available-amenities="availableAmenities"
        @toggle-open-now="toggleOpenNow"
        @locate="locateUser"
        @clear-location="clearLocation"
        @clear-type="clearTypeFilter"
        @toggle-type="toggleTypeFilter"
        @toggle-amenity="toggleAmenity"
        @clear-amenities="clearAmenities"
      />

      <p v-if="facetsError" class="state error">{{ facetsError }}</p>
      <div v-if="loading" class="state">Loading zones…</div>
      <div v-else-if="error" class="state error">{{ error }}</div>
      <div v-else-if="zones.length === 0" class="state">
        No zones found in {{ selectedCityLabel }}
      </div>

      <div v-else class="grid">
        <ZoneCard
          v-for="zone in zones"
          :key="zone.id"
          :zone="zone"
          :is-type-active="activeTypeFilter === zone.type"
          @filter-type="toggleTypeFilter"
        />
      </div>

      <div v-if="!loading && !error && pageData.total > 0" class="pagination">
        <p class="pagination-summary">
          {{ currentRange }}
        </p>

        <div class="pagination-actions">
          <button
            type="button"
            class="pagination-button"
            :disabled="pageData.page === 1"
            @click="goToPage(pageData.page - 1)"
          >
            Previous
          </button>
          <span class="pagination-page">Page {{ pageData.page }} / {{ totalPages }}</span>
          <button
            type="button"
            class="pagination-button"
            :disabled="pageData.page >= totalPages"
            @click="goToPage(pageData.page + 1)"
          >
            Next
          </button>
        </div>
      </div>
    </section>
  </section>
</template>

<style scoped>
.page {
  display: grid;
  gap: 28px;
}

.catalog-shell {
  padding: clamp(24px, 4vw, 38px);
  border-radius: var(--radius-xl);
  background: var(--surface-dark);
  color: white;
  box-shadow: var(--shadow-strong);
}

.state {
  margin-top: 22px;
  padding: 44px 20px;
  border-radius: var(--radius-lg);
  background: rgba(255, 255, 255, 0.1);
  color: rgba(255, 255, 255, 0.9);
  text-align: center;
  font-weight: 700;
}

.state.error {
  background: rgba(220, 38, 38, 0.14);
  color: #fecaca;
}

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 18px;
  margin-top: 22px;
}

.pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
  margin-top: 22px;
  padding-top: 18px;
  border-top: 1px solid rgba(255, 255, 255, 0.12);
}

.pagination-summary {
  margin: 0;
  color: rgba(255, 255, 255, 0.82);
  font-size: 14px;
  font-weight: 700;
}

.pagination-actions {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.pagination-button {
  min-height: 42px;
  padding: 0 16px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.08);
  color: white;
  font-weight: 800;
  cursor: pointer;
  transition:
    transform 0.18s ease,
    border-color 0.18s ease,
    background 0.18s ease,
    opacity 0.18s ease;
}

.pagination-button:hover:not(:disabled) {
  transform: translateY(-1px);
  border-color: rgba(138, 242, 90, 0.4);
  background: rgba(138, 242, 90, 0.12);
}

.pagination-button:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.pagination-page {
  color: rgba(255, 255, 255, 0.88);
  font-size: 14px;
  font-weight: 800;
}

@media (max-width: 640px) {
.hero-panel,
  .catalog-shell {
    padding: 22px;
    border-radius: 28px;
  }

.pagination {
    align-items: flex-start;
  }

}
</style>
