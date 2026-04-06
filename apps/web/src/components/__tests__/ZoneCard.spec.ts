import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { ref } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import ZoneCard from '@/components/ZoneCard.vue'

vi.mock('@/composables/useCurrentMinute', () => ({
  useCurrentMinute: () => ref(new Date('2025-01-13T10:00:00+02:00')),
}))

const DummyView = {
  template: '<div />',
}

async function mountZoneCard() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/',
        name: 'zones',
        component: DummyView,
      },
      {
        path: '/zones/:id',
        name: 'zone-detail',
        component: DummyView,
      },
    ],
  })

  await router.push({
    name: 'zones',
    query: {
      city: 'helsinki',
      q: 'kamppi',
      type: 'commercial',
    },
  })
  await router.isReady()

  return mount(ZoneCard, {
    props: {
      zone: {
        id: 1,
        name: 'Kamppi Center',
        city: 'helsinki',
        type: 'commercial',
        status: 'active',
        hourlyRateEur: 4.5,
        latitude: 60.1685,
        longitude: 24.9318,
        openingHours: {
          weekdays: '06:00-23:30',
          weekends: '08:00-23:30',
        },
      },
    },
    global: {
      plugins: [router],
    },
  })
}

describe('ZoneCard', () => {
  it('renders zone name, status, type, and price', async () => {
    const wrapper = await mountZoneCard()

    expect(wrapper.text()).toContain('Kamppi Center')
    expect(wrapper.text()).toContain('commercial')
    expect(wrapper.text()).toContain('Open now')
    expect(wrapper.text()).toContain('Closes at 23:30')
    expect(wrapper.text()).toContain('€4.50/hour')
    expect(wrapper.text()).toContain('Open details')
    expect(wrapper.get('a.cta').attributes('aria-label')).toBe('View Zone')
  })

  it('emits filter-type when the type chip is clicked', async () => {
    const wrapper = await mountZoneCard()

    await wrapper.get('button.type-filter').trigger('click')

    expect(wrapper.emitted('filter-type')).toEqual([['commercial']])
  })

  it('preserves the current list query in the detail link', async () => {
    const wrapper = await mountZoneCard()

    expect(wrapper.get('a.title-link').attributes('href')).toContain('/zones/1')
    expect(wrapper.get('a.title-link').attributes('href')).toContain('city=helsinki')
    expect(wrapper.get('a.title-link').attributes('href')).toContain('q=kamppi')
  })
})
