<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useCitySelection } from '@/composables/useCitySelection'
import { isCitySlug, type CitySlug } from '@/config/cities'
import { updateZoneCatalogQuery } from '@/composables/useZoneCatalogRoute'

const route = useRoute()
const router = useRouter()
const { cityOptions, selectedCity, selectedCityLabel } = useCitySelection(() => route.query)

const zonesRoute = computed(() => {
  return {
    name: 'zones',
    query: updateZoneCatalogQuery(route.query, { city: selectedCity.value }),
  }
})

function switchCity(nextCity: CitySlug) {
  if (selectedCity.value === nextCity && route.name === 'zones') {
    return
  }

  const nextQuery = updateZoneCatalogQuery(route.query, {
    city: nextCity,
    page: 1,
  })

  if (route.name === 'zone-detail') {
    router.push({ name: 'zones', query: nextQuery })
    return
  }

  router.replace({ query: nextQuery })
}

function handleCityChange(event: Event) {
  const nextCity = event.target instanceof HTMLSelectElement ? event.target.value : null

  if (nextCity !== null && isCitySlug(nextCity)) {
    switchCity(nextCity)
  }
}
</script>

<template>
  <div class="app-shell">
    <header class="site-header">
      <div class="site-header-inner">
        <RouterLink :to="zonesRoute" class="brand">
          <span class="brand-mark" aria-hidden="true">
            <span class="brand-letter">Q</span>
          </span>
          <span class="brand-copy">
            <span class="brand-name">QParking</span>
            <span class="brand-tagline">Simple city parking zones</span>
          </span>
        </RouterLink>

        <nav class="nav" aria-label="Primary">
          <RouterLink :to="zonesRoute" class="nav-link">Zones</RouterLink>
        </nav>

        <label class="city-picker">
          <span class="city-picker-label">City</span>
          <select class="city-select" :value="selectedCity" @change="handleCityChange">
            <option v-for="city in cityOptions" :key="city.slug" :value="city.slug">
              {{ city.label }}
            </option>
          </select>
        </label>
      </div>
    </header>

    <main class="app-main">
      <RouterView />
    </main>

    <footer class="site-footer">
      <p>Parking zone search, comparison, and map preview for {{ selectedCityLabel }}.</p>
    </footer>
  </div>
</template>

<style scoped>
.app-shell {
  min-height: 100vh;
  padding: 18px 18px 28px;
}

.site-header {
  position: sticky;
  top: 14px;
  z-index: 60;
  max-width: 1280px;
  margin: 0 auto;
}

.site-header-inner {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 999px;
  background:
    linear-gradient(135deg, rgba(255, 255, 255, 0.94), rgba(250, 247, 239, 0.9)),
    rgba(255, 255, 255, 0.82);
  border: 1px solid var(--line-soft);
  backdrop-filter: blur(18px);
  box-shadow:
    0 18px 40px rgba(18, 18, 18, 0.08),
    0 4px 12px rgba(18, 18, 18, 0.05);
  overflow-x: auto;
}

.brand {
  display: flex;
  align-items: center;
  gap: 14px;
  flex: 1 1 auto;
  min-width: 0;
}

.brand-mark {
  position: relative;
  display: grid;
  place-items: center;
  width: 42px;
  height: 42px;
  flex-shrink: 0;
  border-radius: 14px;
  background: var(--surface-dark);
  color: white;
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08);
}

.brand-mark::after {
  content: '';
  position: absolute;
  right: 8px;
  bottom: 8px;
  width: 12px;
  height: 12px;
  border-radius: 999px;
  background: var(--accent);
}

.brand-letter {
  font-size: 22px;
  font-weight: 800;
  line-height: 1;
}

.brand-copy {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.brand-name {
  font-size: 19px;
  font-weight: 800;
  line-height: 1;
  letter-spacing: -0.04em;
}

.brand-tagline {
  margin-top: 4px;
  color: var(--text-muted);
  font-size: 12px;
  font-weight: 600;
}

.nav {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-shrink: 0;
}

.nav-link {
  display: inline-flex;
  align-items: center;
  min-height: 42px;
  padding: 0 16px;
  border-radius: 999px;
  background: white;
  border: 1px solid var(--line-soft);
  font-weight: 700;
  transition:
    transform 0.18s ease,
    border-color 0.18s ease,
    background 0.18s ease;
}

.nav-link:hover {
  transform: translateY(-1px);
  border-color: rgba(114, 221, 66, 0.44);
  background: rgba(138, 242, 90, 0.1);
}

.nav-link.router-link-active {
  border-color: transparent;
  background: var(--surface-dark);
  color: white;
}

.city-picker {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 42px;
  padding: 0 12px;
  border-radius: 999px;
  background: rgba(18, 18, 18, 0.05);
  border: 1px solid rgba(18, 18, 18, 0.06);
  flex-shrink: 0;
}

.city-picker::after {
  content: '';
  position: absolute;
  right: 14px;
  top: 50%;
  width: 8px;
  height: 8px;
  margin-top: -6px;
  border-right: 2px solid var(--text-muted);
  border-bottom: 2px solid var(--text-muted);
  transform: rotate(45deg);
  pointer-events: none;
}

.city-picker-label {
  color: var(--text-subtle);
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.city-select {
  min-width: 136px;
  padding: 0 26px 0 0;
  border: none;
  background: transparent;
  color: var(--text-strong);
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  appearance: none;
}

.city-select:focus {
  outline: none;
}

.city-select::-ms-expand {
  display: none;
}

.app-main {
  max-width: 1280px;
  margin: 0 auto;
  padding: 26px 0 0;
}

.site-footer {
  max-width: 1280px;
  margin: 20px auto 0;
  padding: 10px 4px 0;
  text-align: center;
  font-size: 13px;
  font-weight: 600;
  color: var(--text-subtle);
}

@media (max-width: 960px) {
  .app-shell {
    padding: 14px 14px 24px;
  }

  .site-header {
    top: 10px;
  }

  .site-header-inner {
    padding: 11px 12px;
  }
}

@media (max-width: 640px) {
  .brand-name {
    font-size: 18px;
  }

  .brand-tagline {
    display: none;
  }

  .nav-link {
    padding: 0 14px;
  }

  .city-picker {
    padding-left: 10px;
  }

  .city-picker-label {
    display: none;
  }

  .city-select {
    min-width: 104px;
    font-size: 13px;
  }
}
</style>
