import { RouterLinkStub, mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ZoneCard from '@/components/ZoneCard.vue'

describe('ZoneCard', () => {
  it('renders zone name, status, type, and price', () => {
    const wrapper = mount(ZoneCard, {
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
        },
      },
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    })

    expect(wrapper.text()).toContain('Kamppi Center')
    expect(wrapper.text()).toContain('commercial')
    expect(wrapper.text()).toContain('active')
    expect(wrapper.text()).toContain('€4.50/hour')
    expect(wrapper.text()).toContain('Open details')
    expect(wrapper.get('a.cta').attributes('aria-label')).toBe('View Zone')
  })

  it('emits filter-type when the type chip is clicked', async () => {
    const wrapper = mount(ZoneCard, {
      props: {
        zone: {
          id: 2,
          name: 'Test Zone',
          city: 'helsinki',
          type: 'street',
          status: 'inactive',
          hourlyRateEur: 2,
          latitude: 60,
          longitude: 24,
        },
      },
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    })

    await wrapper.get('button.type-filter').trigger('click')

    expect(wrapper.emitted('filter-type')).toEqual([['street']])
  })

  it('renders a direct map link for view zone', () => {
    const wrapper = mount(ZoneCard, {
      props: {
        zone: {
          id: 3,
          name: 'Map Zone',
          city: 'helsinki',
          type: 'street',
          status: 'active',
          hourlyRateEur: 3.5,
          latitude: 60.167,
          longitude: 24.9475,
        },
      },
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    })

    expect(wrapper.get('a.cta').attributes('href')).toContain('openstreetmap.org')
    expect(wrapper.get('a.cta').attributes('href')).toContain('60.167')
  })
})
