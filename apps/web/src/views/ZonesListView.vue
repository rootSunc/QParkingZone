<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { fetchZones, type ZonesPage } from '@/api/zones'
import { useCitySelection } from '@/composables/useCitySelection'
import {
  defaultZonePageSize,
  parseZoneCatalogQuery,
  updateZoneCatalogQuery,
  type ZoneCatalogQueryState,
  type ZoneSort,
} from '@/composables/useZoneCatalogRoute'
import ZoneCard from '@/components/ZoneCard.vue'

const route = useRoute()
const router = useRouter()
const pageData = ref<ZonesPage>({
  items: [],
  total: 0,
  page: 1,
  limit: defaultZonePageSize,
})
const loading = ref(false)
const error = ref('')
const { selectedCityLabel } = useCitySelection(() => route.query)
const catalogState = computed(() => parseZoneCatalogQuery(route.query))
let activeRequestId = 0
let activeController: AbortController | null = null

const zones = computed(() => {
  return pageData.value.items
})

const availableTypes = computed(() => {
  return [...new Set(zones.value.map((zone) => zone.type))]
})

const searchQuery = computed({
  get() {
    return catalogState.value.q
  },
  set(value: string) {
    replaceCatalogQuery({
      q: value,
      page: 1,
    })
  },
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

const activeZones = computed(() => {
  return zones.value.filter((zone) => zone.status === 'active').length
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

function isAbortError(error: unknown): boolean {
  return error instanceof Error && error.name === 'AbortError'
}

async function loadZones(state: ZoneCatalogQueryState) {
  const requestId = ++activeRequestId
  activeController?.abort()
  const controller = new AbortController()
  activeController = controller
  loading.value = true
  error.value = ''

  try {
    const data = await fetchZones(state, controller.signal)

    if (requestId !== activeRequestId) {
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
    if (requestId !== activeRequestId || isAbortError(err)) {
      return
    }

    error.value = err instanceof Error ? err.message : 'Failed to load zones'
  } finally {
    if (requestId === activeRequestId) {
      loading.value = false

      if (activeController === controller) {
        activeController = null
      }
    }
  }
}

watch(catalogState, loadZones, { immediate: true })

onUnmounted(() => {
  activeRequestId += 1
  activeController?.abort()
  activeController = null
})
</script>

<template>
  <section class="page">
    <section class="hero-panel">
      <div class="hero-copy">
        <p class="eyebrow">Parking app</p>
        <h1>Simple parking for every {{ selectedCityLabel }} zone.</h1>
        <p class="hero-text">
          Search {{ selectedCityLabel }} parking areas, compare pricing fast, and jump into the
          exact zone details before you arrive.
        </p>

        <div class="hero-actions">
          <button type="button" class="primary-action" @click="scrollToZones">Explore zones</button>
          <p class="hero-note">Built for fast scanning and quick map handoff.</p>
        </div>

        <div class="hero-metrics">
          <article class="metric">
            <span class="metric-value">{{ pageData.total }}</span>
            <span class="metric-label">matching zones</span>
          </article>
          <article class="metric">
            <span class="metric-value">{{ zones.length }}</span>
            <span class="metric-label">shown on this page</span>
          </article>
          <article class="metric">
            <span class="metric-value">€{{ averageRate.toFixed(2) }}</span>
            <span class="metric-label">page avg hourly rate</span>
          </article>
        </div>
      </div>

      <div class="hero-preview" aria-hidden="true">
        <div class="phone-frame">
          <div class="phone-top">
            <span class="phone-pill">Live nearby zones</span>
            <span class="phone-pill phone-pill-dark">Ready</span>
          </div>

          <div class="phone-map">
            <span class="map-route"></span>
            <span class="map-pin map-pin-main"></span>
            <span class="map-pin map-pin-secondary"></span>
            <span class="map-pin map-pin-tertiary"></span>
          </div>

          <div class="phone-card">
            <p class="phone-overline">Highlighted zone</p>
            <h2>{{ previewZone?.name ?? 'Loading nearby zone' }}</h2>
            <div class="phone-rate">
              {{
                previewZone
                  ? `€${previewZone.hourlyRateEur.toFixed(2)}/hour`
                  : 'Syncing live pricing'
              }}
            </div>
            <div class="phone-pills">
              <span>{{ selectedCityLabel }}</span>
              <span>{{ previewZone?.type ?? 'city parking' }}</span>
              <span>{{ previewZone?.status ?? 'loading' }}</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section id="zones-grid" class="catalog-shell">
      <div class="catalog-header">
        <div>
          <p class="catalog-kicker">Find a parking zone</p>
          <h2>Search, sort, and compare across {{ selectedCityLabel }}.</h2>
          <p class="catalog-copy">
            The layout stays compact and high contrast so pricing and status remain visible at a
            glance.
          </p>
        </div>
        <span class="catalog-count">{{ pageData.total }} results</span>
      </div>

      <div class="toolbar">
        <label class="field">
          <span>Search</span>
          <input v-model="searchQuery" placeholder="Search zones..." class="search" />
        </label>

        <label class="field field-small">
          <span>Sort</span>
          <select v-model="sortBy" class="select">
            <option value="name">Sort by name</option>
            <option value="price_desc">Price: high to low</option>
            <option value="price_asc">Price: low to high</option>
          </select>
        </label>
      </div>

      <div v-if="activeTypeFilter" class="active-filters">
        <span class="active-filters-label">Filtered by type</span>
        <button type="button" class="active-filter-chip" @click="clearTypeFilter">
          {{ activeTypeFilter }} ×
        </button>
      </div>

      <div v-else class="type-hints">
        <span class="type-hints-label">Quick filters</span>
        <button
          v-for="type in availableTypes"
          :key="type"
          type="button"
          class="type-hint-chip"
          @click="toggleTypeFilter(type)"
        >
          {{ type }}
        </button>
      </div>

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
          <span class="pagination-active">{{ activeZones }} active on this page</span>
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

.hero-panel {
  display: grid;
  grid-template-columns: minmax(0, 1.15fr) minmax(300px, 0.85fr);
  gap: 24px;
  padding: clamp(28px, 4vw, 42px);
  border-radius: var(--radius-xl);
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.78), rgba(255, 255, 255, 0.9));
  border: 1px solid var(--line-soft);
  box-shadow: var(--shadow-soft);
}

.hero-copy {
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.eyebrow {
  margin: 0 0 10px;
  color: var(--text-muted);
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.16em;
  text-transform: uppercase;
}

.hero-copy h1 {
  margin: 0;
  max-width: 11ch;
  font-size: clamp(3rem, 6vw, 5.4rem);
  line-height: 0.95;
  letter-spacing: -0.06em;
}

.hero-text {
  max-width: 58ch;
  margin: 18px 0 0;
  color: var(--text-muted);
  font-size: 18px;
}

.hero-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 14px 18px;
  margin-top: 28px;
}

.primary-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 52px;
  padding: 0 22px;
  border: none;
  border-radius: 999px;
  background: var(--accent);
  color: var(--surface-dark);
  cursor: pointer;
  font-weight: 800;
  transition:
    transform 0.18s ease,
    box-shadow 0.18s ease,
    background 0.18s ease;
}

.primary-action:hover {
  transform: translateY(-2px);
  background: var(--accent-strong);
  box-shadow: 0 20px 40px rgba(114, 221, 66, 0.28);
}

.hero-note {
  margin: 0;
  color: var(--text-subtle);
  font-size: 14px;
  font-weight: 700;
}

.hero-metrics {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
  margin-top: 30px;
}

.metric {
  padding: 18px 20px;
  border-radius: var(--radius-md);
  background: rgba(255, 255, 255, 0.78);
  border: 1px solid rgba(18, 18, 18, 0.08);
}

.metric-value {
  display: block;
  font-size: clamp(26px, 3vw, 34px);
  font-weight: 800;
  line-height: 1;
  letter-spacing: -0.05em;
}

.metric-label {
  display: block;
  margin-top: 8px;
  color: var(--text-muted);
  font-size: 13px;
  font-weight: 700;
}

.hero-preview {
  display: flex;
  align-items: center;
  justify-content: center;
}

.phone-frame {
  position: relative;
  width: min(100%, 360px);
  min-height: 460px;
  padding: 16px;
  border-radius: 40px;
  background: var(--surface-dark);
  box-shadow: var(--shadow-strong);
}

.phone-frame::before {
  content: '';
  position: absolute;
  inset: 10px;
  border-radius: 32px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  pointer-events: none;
}

.phone-top {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}

.phone-pill {
  display: inline-flex;
  align-items: center;
  padding: 8px 12px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.12);
  color: white;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.03em;
}

.phone-pill-dark {
  background: var(--accent);
  color: var(--surface-dark);
}

.phone-map {
  position: relative;
  height: 232px;
  border-radius: 28px;
  background:
    radial-gradient(circle at 20% 25%, rgba(138, 242, 90, 0.14), transparent 18%),
    linear-gradient(145deg, #1f1f1f 0%, #111111 100%);
  overflow: hidden;
}

.phone-map::before,
.phone-map::after {
  content: '';
  position: absolute;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.08);
}

.phone-map::before {
  width: 180px;
  height: 18px;
  left: -20px;
  top: 110px;
  transform: rotate(22deg);
}

.phone-map::after {
  width: 150px;
  height: 18px;
  right: -30px;
  top: 48px;
  transform: rotate(-28deg);
}

.map-route {
  position: absolute;
  left: 52px;
  top: 34px;
  width: 170px;
  height: 170px;
  border: 3px dashed rgba(255, 255, 255, 0.28);
  border-radius: 50% 46% 52% 44%;
}

.map-pin {
  position: absolute;
  width: 18px;
  height: 18px;
  border-radius: 999px;
  background: white;
  box-shadow: 0 0 0 8px rgba(255, 255, 255, 0.08);
}

.map-pin::after {
  content: '';
  position: absolute;
  inset: 4px;
  border-radius: 999px;
  background: var(--accent);
}

.map-pin-main {
  left: 88px;
  top: 66px;
}

.map-pin-secondary {
  right: 60px;
  top: 102px;
}

.map-pin-tertiary {
  left: 132px;
  bottom: 34px;
}

.phone-card {
  margin-top: -40px;
  margin-left: auto;
  width: calc(100% - 28px);
  min-height: 192px;
  padding: 20px;
  border-radius: 28px;
  background: white;
  box-shadow: 0 24px 40px rgba(18, 18, 18, 0.2);
}

.phone-overline {
  margin: 0;
  color: var(--text-subtle);
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.phone-card h2 {
  margin: 10px 0 0;
  min-height: 56px;
  overflow: hidden;
  font-size: 28px;
  line-height: 1;
  letter-spacing: -0.05em;
  text-wrap: balance;
}

.phone-rate {
  margin-top: 12px;
  font-size: 18px;
  font-weight: 800;
}

.phone-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 14px;
}

.phone-pills span {
  display: inline-flex;
  align-items: center;
  padding: 7px 10px;
  border-radius: 999px;
  background: rgba(18, 18, 18, 0.06);
  color: var(--text-muted);
  font-size: 12px;
  font-weight: 700;
}

.catalog-shell {
  padding: clamp(24px, 4vw, 38px);
  border-radius: var(--radius-xl);
  background: var(--surface-dark);
  color: white;
  box-shadow: var(--shadow-strong);
}

.catalog-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 18px;
}

.catalog-kicker {
  margin: 0 0 10px;
  color: rgba(255, 255, 255, 0.78);
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.16em;
  text-transform: uppercase;
}

.catalog-header h2 {
  margin: 0;
  max-width: 13ch;
  font-size: clamp(2rem, 3.8vw, 3.6rem);
  line-height: 0.95;
  letter-spacing: -0.05em;
}

.catalog-copy {
  max-width: 52ch;
  margin: 14px 0 0;
  color: rgba(255, 255, 255, 0.84);
  font-size: 16px;
}

.catalog-count {
  display: inline-flex;
  align-items: center;
  align-self: flex-start;
  min-height: 42px;
  padding: 0 16px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.12);
  color: rgba(255, 255, 255, 0.9);
  font-size: 14px;
  font-weight: 700;
}

.toolbar {
  display: flex;
  align-items: flex-end;
  gap: 12px;
  flex-wrap: wrap;
  margin-top: 28px;
}

.field {
  display: flex;
  flex: 1 1 280px;
  flex-direction: column;
  gap: 10px;
}

.field span {
  color: rgba(255, 255, 255, 0.84);
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.field-small {
  flex: 0 1 240px;
}

.search {
  flex: 1;
  min-width: 180px;
  min-height: 54px;
  padding: 0 16px;
  border: 1px solid transparent;
  border-radius: 18px;
  background: white;
  color: var(--surface-dark);
  font-size: 15px;
  font-weight: 700;
}

.search::placeholder {
  color: #7a7469;
  opacity: 1;
}

.search:focus {
  outline: none;
  border-color: rgba(138, 242, 90, 0.6);
  box-shadow: 0 0 0 4px rgba(138, 242, 90, 0.16);
}

.select {
  min-height: 54px;
  padding: 0 16px;
  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 18px;
  background: rgba(255, 255, 255, 0.14);
  color: rgba(255, 255, 255, 0.94);
  font-size: 15px;
  font-weight: 700;
}

.select:focus {
  outline: none;
  border-color: rgba(138, 242, 90, 0.42);
}

.select option {
  color: var(--surface-dark);
}

.active-filters,
.type-hints {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  margin-top: 18px;
}

.active-filters-label,
.type-hints-label {
  color: rgba(255, 255, 255, 0.84);
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.active-filter-chip,
.type-hint-chip {
  display: inline-flex;
  align-items: center;
  min-height: 38px;
  padding: 0 14px;
  border: 1px solid transparent;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 800;
  text-transform: capitalize;
  cursor: pointer;
  transition:
    background 0.18s ease,
    border-color 0.18s ease,
    color 0.18s ease;
}

.active-filter-chip {
  background: rgba(138, 242, 90, 0.18);
  color: #c8f2ad;
  border-color: rgba(138, 242, 90, 0.32);
}

.type-hint-chip {
  background: rgba(255, 255, 255, 0.08);
  color: rgba(255, 255, 255, 0.88);
}

.active-filter-chip:hover,
.active-filter-chip:focus-visible,
.type-hint-chip:hover,
.type-hint-chip:focus-visible {
  border-color: rgba(138, 242, 90, 0.42);
  background: rgba(138, 242, 90, 0.14);
  color: white;
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

.pagination-active {
  color: #c8f2ad;
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

@media (max-width: 1080px) {
  .hero-panel {
    grid-template-columns: 1fr;
  }

  .hero-copy h1,
  .catalog-header h2 {
    max-width: none;
  }
}

@media (max-width: 720px) {
  .hero-metrics {
    grid-template-columns: 1fr;
  }

  .catalog-header {
    flex-direction: column;
  }
}

@media (max-width: 640px) {
  .hero-panel,
  .catalog-shell {
    padding: 22px;
    border-radius: 28px;
  }

  .phone-card {
    width: 100%;
  }

  .catalog-count {
    align-self: stretch;
    justify-content: center;
  }

  .field,
  .field-small {
    flex-basis: 100%;
  }

  .active-filters,
  .type-hints {
    align-items: flex-start;
  }

  .pagination {
    align-items: flex-start;
  }
}
</style>
