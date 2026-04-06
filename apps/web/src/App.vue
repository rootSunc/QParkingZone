<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useCitySelection } from '@/composables/useCitySelection'
import type { CitySlug } from '@/config/cities'
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
          <div class="city-switcher" aria-label="City switcher">
            <button
              v-for="city in cityOptions"
              :key="city.slug"
              type="button"
              class="city-chip"
              :class="{ 'city-chip-active': selectedCity === city.slug }"
              @click="switchCity(city.slug)"
            >
              {{ city.label }}
            </button>
          </div>
        </nav>
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
  max-width: 1280px;
  margin: 0 auto;
}

.site-header-inner {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 18px;
  padding: 14px 16px 14px 14px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.78);
  border: 1px solid var(--line-soft);
  backdrop-filter: blur(18px);
  box-shadow: var(--shadow-soft);
}

.brand {
  display: flex;
  align-items: center;
  gap: 14px;
  min-width: 0;
  flex-shrink: 1;
}

.brand-mark {
  position: relative;
  display: grid;
  place-items: center;
  width: 48px;
  height: 48px;
  flex-shrink: 0;
  border-radius: 16px;
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
  font-size: 20px;
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
  padding: 11px 18px;
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

.city-switcher {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px;
  border-radius: 999px;
  background: rgba(18, 18, 18, 0.06);
  border: 1px solid rgba(18, 18, 18, 0.04);
}

.city-chip {
  min-height: 38px;
  padding: 0 14px;
  border: none;
  border-radius: 999px;
  background: transparent;
  color: var(--text-muted);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition:
    transform 0.18s ease,
    background 0.18s ease,
    color 0.18s ease,
    box-shadow 0.18s ease;
}

.city-chip:hover {
  transform: translateY(-1px);
  color: var(--text-strong);
}

.city-chip-active {
  background: white;
  color: var(--surface-dark);
  box-shadow: 0 6px 16px rgba(18, 18, 18, 0.08);
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

  .site-header-inner {
    border-radius: 30px;
  }
}

@media (max-width: 640px) {
  .site-header-inner {
    flex-direction: column;
    align-items: flex-start;
    padding: 14px;
  }

  .brand {
    width: 100%;
  }

  .brand-name {
    font-size: 18px;
  }

  .nav {
    width: 100%;
    flex-wrap: wrap;
  }

  .nav-link {
    flex: 1 1 160px;
    text-align: center;
  }

  .city-switcher {
    width: 100%;
    justify-content: center;
    flex-wrap: wrap;
  }
}
</style>
