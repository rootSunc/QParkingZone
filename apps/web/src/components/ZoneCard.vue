<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import type { ZoneSummary } from '@/api/zones'

const props = defineProps<{
  zone: ZoneSummary
  isTypeActive?: boolean
}>()

const emit = defineEmits<{
  'filter-type': [type: string]
}>()

const detailRoute = computed(() => {
  return `/zones/${props.zone.id}`
})

const mapUrl = computed(() => {
  return `https://www.openstreetmap.org/?mlat=${props.zone.latitude}&mlon=${props.zone.longitude}#map=16/${props.zone.latitude}/${props.zone.longitude}`
})

function toggleTypeFilter() {
  emit('filter-type', props.zone.type)
}
</script>

<template>
  <article class="zone-card">
    <div class="card-top">
      <p class="eyebrow">Zone {{ zone.id }}</p>
      <span class="status-pill" :class="zone.status === 'active' ? 'active' : 'inactive'">
        {{ zone.status }}
      </span>
    </div>

    <RouterLink :to="detailRoute" class="title-link">
      <h2 class="title">{{ zone.name }}</h2>
    </RouterLink>

    <p class="body-copy">
      Compare the hourly rate, check current status, and open the detailed map view in one tap.
    </p>

    <div class="meta">
      <button
        type="button"
        class="pill type-filter"
        :class="{ 'type-filter-active': isTypeActive }"
        :aria-pressed="isTypeActive ? 'true' : 'false'"
        @click="toggleTypeFilter"
      >
        {{ zone.type }}
      </button>

      <RouterLink :to="detailRoute" class="pill outline action-link"> Open details </RouterLink>
    </div>

    <div class="card-footer">
      <div class="price-block">
        <span class="price-label">Hourly rate</span>
        <span class="price">€{{ zone.hourlyRateEur.toFixed(2) }}/hour</span>
      </div>

      <a :href="mapUrl" target="_blank" rel="noreferrer" class="cta" aria-label="View Zone">
        <span>View</span>
        <span>Zone</span>
      </a>
    </div>
  </article>
</template>

<style scoped>
.zone-card {
  position: relative;
  width: 100%;
  min-height: 100%;
  padding: 22px;
  border-radius: 28px;
  background: rgba(255, 255, 255, 0.96);
  border: 1px solid rgba(18, 18, 18, 0.08);
  box-shadow: 0 18px 34px rgba(18, 18, 18, 0.08);
  color: var(--text-strong);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  transition:
    transform 0.18s ease,
    box-shadow 0.18s ease,
    border-color 0.18s ease;
}

.zone-card::before {
  content: '';
  position: absolute;
  left: 22px;
  top: 0;
  width: 56px;
  height: 4px;
  border-radius: 999px;
  background: var(--accent);
}

.zone-card:hover {
  transform: translateY(-6px);
  border-color: rgba(138, 242, 90, 0.3);
  box-shadow: 0 24px 48px rgba(18, 18, 18, 0.14);
}

.card-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
}

.eyebrow {
  margin: 0;
  color: #847d72;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.status-pill {
  display: inline-flex;
  align-items: center;
  padding: 7px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 800;
  text-transform: capitalize;
}

.status-pill.active {
  background: var(--accent-soft);
  color: #315b1f;
}

.status-pill.inactive {
  background: rgba(220, 38, 38, 0.1);
  color: var(--danger-text);
}

.title-link {
  margin-top: 18px;
  align-self: flex-start;
}

.title-link:hover .title,
.title-link:focus-visible .title {
  color: #35581b;
}

.title {
  margin: 0;
  font-size: clamp(26px, 2.2vw, 34px);
  line-height: 1;
  letter-spacing: -0.05em;
  color: var(--accent-ink);
  transition: color 0.18s ease;
}

.body-copy {
  margin: 12px 0 0;
  color: #534e45;
  font-size: 15px;
}

.meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: 18px 0 0;
}

.pill {
  display: inline-flex;
  align-items: center;
  min-height: 40px;
  padding: 0 14px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 800;
  text-transform: capitalize;
}

.type-filter {
  border: 1px solid transparent;
  background: rgba(18, 18, 18, 0.08);
  color: #5b564d;
  cursor: pointer;
  transition:
    background 0.18s ease,
    color 0.18s ease,
    border-color 0.18s ease;
}

.type-filter:hover,
.type-filter:focus-visible {
  background: rgba(138, 242, 90, 0.16);
  border-color: rgba(138, 242, 90, 0.4);
  color: #36591c;
}

.type-filter-active {
  background: rgba(138, 242, 90, 0.22);
  border-color: rgba(138, 242, 90, 0.5);
  color: #2f5118;
}

.outline {
  border: 1px solid rgba(18, 18, 18, 0.12);
  color: #7d766c;
  transition:
    border-color 0.18s ease,
    color 0.18s ease,
    background 0.18s ease;
}

.action-link:hover,
.action-link:focus-visible {
  border-color: rgba(67, 109, 36, 0.3);
  background: rgba(67, 109, 36, 0.06);
  color: var(--accent-ink);
}

.card-footer {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 16px;
  margin-top: 22px;
}

.price-block {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.price-label {
  color: #847d72;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.price {
  font-size: clamp(22px, 1.8vw, 30px);
  font-weight: 800;
  letter-spacing: -0.05em;
  color: var(--text-strong);
}

.cta {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 1px;
  min-height: 34px;
  min-width: 82px;
  padding: 0 12px;
  border-radius: 999px;
  background: var(--surface-dark);
  color: white;
  font-size: 11px;
  font-weight: 800;
  line-height: 1;
  letter-spacing: -0.01em;
  text-align: center;
  transition:
    transform 0.18s ease,
    background 0.18s ease;
}

.cta span {
  display: block;
  width: 100%;
}

.cta:hover,
.cta:focus-visible {
  transform: translateY(-1px);
  background: #272727;
}
</style>
