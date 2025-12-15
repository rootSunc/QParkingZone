<script setup lang="ts">
import type { ZoneSort } from '@/composables/useZoneCatalogRoute'

const searchQuery = defineModel<string>('searchQuery', { required: true })
const sortBy = defineModel<ZoneSort>('sortBy', { required: true })
const radius = defineModel<number | null>('radius', { required: true })

defineProps<{
  selectedCityLabel: string
  total: number
  hasLocation: boolean
  locating: boolean
  locationError: string
  openNowOnly: boolean
  activeTypeFilter: string | null
  activeAmenities: string[]
  availableTypes: string[]
  availableAmenities: string[]
}>()

const emit = defineEmits<{
  'toggle-open-now': []
  locate: []
  'clear-location': []
  'clear-type': []
  'toggle-type': [type: string]
  'toggle-amenity': [amenity: string]
  'clear-amenities': []
}>()
</script>

<template>
  <div>
      <div class="catalog-header">
        <div>
          <p class="catalog-kicker">Find a parking zone</p>
          <h2>Search, sort, and compare across {{ selectedCityLabel }}.</h2>
          <p class="catalog-copy">
            The layout stays compact and high contrast so pricing and status remain visible at a
            glance.
          </p>
        </div>
        <span class="catalog-count">{{ total }} results</span>
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
            <option value="distance_asc" :disabled="!hasLocation">Distance: nearest first</option>
          </select>
        </label>

        <label v-if="hasLocation" class="field field-small">
          <span>Radius</span>
          <select v-model="radius" class="select">
            <option :value="null">Any distance</option>
            <option :value="1.0">Within 1 km</option>
            <option :value="2.5">Within 2.5 km</option>
            <option :value="5.0">Within 5 km</option>
            <option :value="10.0">Within 10 km</option>
          </select>
        </label>
      </div>

      <div class="catalog-actions">
        <button
          type="button"
          class="catalog-chip"
          :class="{ 'catalog-chip-active': openNowOnly }"
          :aria-pressed="openNowOnly ? 'true' : 'false'"
          @click="emit('toggle-open-now')"
        >
          Open now
        </button>

        <button
          v-if="!hasLocation"
          type="button"
          class="catalog-chip catalog-chip-ghost"
          :disabled="locating"
          @click="emit('locate')"
        >
          {{ locating ? 'Locating…' : 'Use my location' }}
        </button>

        <button
          v-else
          type="button"
          class="catalog-chip catalog-chip-ghost"
          @click="emit('clear-location')"
        >
          Clear location
        </button>

        <span v-if="hasLocation" class="catalog-inline-note">Distance is now available in the list.</span>
      </div>

      <p v-if="locationError" class="catalog-feedback">{{ locationError }}</p>

      <div v-if="activeTypeFilter || openNowOnly || hasLocation || activeAmenities.length > 0" class="active-filters">
        <span class="active-filters-label">Active filters</span>
        <button v-if="activeTypeFilter" type="button" class="active-filter-chip" @click="emit('clear-type')">
          {{ activeTypeFilter }} ×
        </button>
        <button v-if="openNowOnly" type="button" class="active-filter-chip" @click="emit('toggle-open-now')">
          open now ×
        </button>
        <button v-if="hasLocation" type="button" class="active-filter-chip" @click="emit('clear-location')">
          nearby ×
        </button>
        <button
          v-for="amenity in activeAmenities"
          :key="amenity"
          type="button"
          class="active-filter-chip"
          @click="emit('toggle-amenity', amenity)"
        >
          {{ amenity }} ×
        </button>
        <button v-if="activeAmenities.length > 1" type="button" class="active-filter-chip active-filter-chip-clear" @click="emit('clear-amenities')">
          clear amenities
        </button>
      </div>

      <div class="type-hints">
        <span class="type-hints-label">Zone Types</span>
        <button
          v-for="type in availableTypes"
          :key="type"
          type="button"
          class="type-hint-chip"
          :class="{ 'type-hint-chip-active': type === activeTypeFilter }"
          @click="emit('toggle-type', type)"
        >
          {{ type }}
        </button>
      </div>

      <div class="type-hints type-hints-spaced">
        <span class="type-hints-label">Amenities</span>
        <button
          v-for="amenity in availableAmenities"
          :key="amenity"
          type="button"
          class="type-hint-chip"
          :class="{ 'type-hint-chip-active': activeAmenities.includes(amenity) }"
          @click="emit('toggle-amenity', amenity)"
        >
          {{ amenity }}
        </button>
      </div>

  </div>
</template>

<style scoped>
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

.catalog-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  margin-top: 18px;
}

.catalog-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 40px;
  padding: 0 16px;
  border: 1px solid transparent;
  border-radius: 999px;
  background: rgba(138, 242, 90, 0.14);
  color: white;
  font-size: 13px;
  font-weight: 800;
  cursor: pointer;
  transition:
    transform 0.18s ease,
    border-color 0.18s ease,
    background 0.18s ease,
    opacity 0.18s ease;
}

.catalog-chip:hover:not(:disabled) {
  transform: translateY(-1px);
  border-color: rgba(138, 242, 90, 0.4);
}

.catalog-chip:disabled {
  opacity: 0.55;
  cursor: wait;
}

.catalog-inline-note,
.catalog-feedback {
  margin: 0;
  color: rgba(255, 255, 255, 0.78);
  font-size: 13px;
  font-weight: 700;
}

.catalog-feedback {
  margin-top: 10px;
  color: #fecaca;
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

@media (max-width: 1080px) {
.hero-copy h1,
  .catalog-header h2 {
    max-width: none;
  }

}

@media (max-width: 720px) {
.catalog-header {
    flex-direction: column;
  }

}

@media (max-width: 640px) {
.catalog-count {
    align-self: stretch;
    justify-content: center;
  }

.field,
  .field-small {
    flex-basis: 100%;
  }

.active-filters,
  .type-hints,
  .catalog-actions {
    align-items: flex-start;
  }

}
</style>
