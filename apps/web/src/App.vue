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
        <div class="brand-row">
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
            <span class="header-presence">{{ selectedCityLabel }} live catalog</span>
          </nav>
        </div>

        <div class="city-rail">
          <div class="city-rail-copy">
            <span class="city-rail-label">Switch city</span>
            <p class="city-rail-title">{{ selectedCityLabel }} parking zones</p>
          </div>

          <div class="city-switcher" aria-label="City switcher">
            <button
              v-for="city in cityOptions"
              :key="city.slug"
              type="button"
              class="city-chip"
              :class="{ 'city-chip-active': selectedCity === city.slug }"
              @click="switchCity(city.slug)"
            >
              <span class="city-chip-label">{{ city.label }}</span>
              <span class="city-chip-meta">
                {{ selectedCity === city.slug ? 'Current city' : 'View zones' }}
              </span>
            </button>
          </div>
        </div>
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
  display: grid;
  gap: 18px;
  padding: 16px;
  border-radius: 32px;
  background:
    linear-gradient(135deg, rgba(255, 255, 255, 0.94), rgba(250, 247, 239, 0.9)),
    rgba(255, 255, 255, 0.82);
  border: 1px solid var(--line-soft);
  backdrop-filter: blur(18px);
  box-shadow:
    0 18px 40px rgba(18, 18, 18, 0.08),
    0 4px 12px rgba(18, 18, 18, 0.05);
}

.brand-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 18px;
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
  flex-wrap: wrap;
  justify-content: flex-end;
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

.header-presence {
  display: inline-flex;
  align-items: center;
  min-height: 42px;
  padding: 0 14px;
  border-radius: 999px;
  background: rgba(18, 18, 18, 0.05);
  color: var(--text-muted);
  font-size: 13px;
  font-weight: 700;
}

.city-rail {
  display: grid;
  grid-template-columns: minmax(0, 220px) minmax(0, 1fr);
  gap: 16px;
  align-items: center;
  padding: 8px;
  border-radius: 24px;
  background:
    linear-gradient(135deg, rgba(18, 18, 18, 0.04), rgba(138, 242, 90, 0.12)),
    rgba(255, 255, 255, 0.74);
  border: 1px solid rgba(18, 18, 18, 0.05);
}

.city-rail-copy {
  padding: 8px 10px 8px 12px;
}

.city-rail-label {
  display: inline-block;
  color: var(--text-subtle);
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.16em;
  text-transform: uppercase;
}

.city-rail-title {
  margin: 8px 0 0;
  color: var(--text-strong);
  font-size: 20px;
  font-weight: 800;
  letter-spacing: -0.04em;
}

.city-switcher {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}

.city-chip {
  display: grid;
  gap: 3px;
  align-items: start;
  justify-items: start;
  min-height: 72px;
  padding: 14px 16px;
  border: none;
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.76);
  color: var(--text-strong);
  font-size: 13px;
  font-weight: 700;
  text-align: left;
  cursor: pointer;
  transition:
    transform 0.18s ease,
    background 0.18s ease,
    color 0.18s ease,
    box-shadow 0.18s ease,
    border-color 0.18s ease;
  border: 1px solid rgba(18, 18, 18, 0.04);
}

.city-chip:hover {
  transform: translateY(-1px);
  color: var(--text-strong);
  box-shadow: 0 12px 24px rgba(18, 18, 18, 0.08);
}

.city-chip-label {
  font-size: 15px;
  font-weight: 800;
}

.city-chip-meta {
  color: var(--text-muted);
  font-size: 12px;
  font-weight: 700;
}

.city-chip-active {
  background: var(--surface-dark);
  color: var(--surface-dark);
  color: white;
  border-color: transparent;
  box-shadow: 0 20px 34px rgba(18, 18, 18, 0.18);
}

.city-chip-active .city-chip-meta {
  color: rgba(255, 255, 255, 0.72);
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
    border-radius: 30px;
  }

  .brand-row {
    flex-direction: column;
    align-items: flex-start;
  }

  .nav {
    width: 100%;
    justify-content: space-between;
  }

  .city-rail {
    grid-template-columns: 1fr;
  }

  .city-switcher {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .site-header-inner {
    gap: 14px;
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
    gap: 8px;
    justify-content: flex-start;
  }

  .nav-link {
    flex: 1 1 132px;
    text-align: center;
  }

  .header-presence {
    width: 100%;
    justify-content: center;
  }

  .city-rail-copy {
    padding: 4px 6px 2px;
  }

  .city-rail-title {
    font-size: 18px;
  }

  .city-switcher {
    width: 100%;
    grid-template-columns: 1fr;
  }

  .city-chip {
    min-height: 64px;
  }
}
</style>
