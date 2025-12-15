<script setup lang="ts">
import type { ZoneSummary } from '@/api/zones'

defineProps<{
  selectedCityLabel: string
  total: number
  shownCount: number
  averageRate: number
  previewZone: ZoneSummary | null
  hasLocation: boolean
}>()

const emit = defineEmits<{
  explore: []
}>()
</script>

<template>
    <section class="hero-panel">
      <div class="hero-copy">
        <p class="eyebrow">Parking app</p>
        <h1>Simple parking for every {{ selectedCityLabel }} zone.</h1>
        <p class="hero-text">
          Search {{ selectedCityLabel }} parking areas, compare pricing fast, and jump into the
          exact zone details before you arrive.
        </p>

        <div class="hero-actions">
          <button type="button" class="primary-action" @click="emit('explore')">Explore zones</button>
          <p class="hero-note">Built for fast scanning and quick map handoff.</p>
        </div>

        <div class="hero-metrics">
          <article class="metric">
            <span class="metric-value">{{ total }}</span>
            <span class="metric-label">matching zones</span>
          </article>
          <article class="metric">
            <span class="metric-value">{{ shownCount }}</span>
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
              <span>{{ hasLocation && previewZone?.distanceKm !== undefined ? `${previewZone.distanceKm.toFixed(1)} km away` : previewZone?.status ?? 'loading' }}</span>
            </div>
          </div>
        </div>
      </div>
    </section>
</template>

<style scoped>
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

}
</style>
