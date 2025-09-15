import { enableAutoUnmount, RouterLinkStub, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import ZonesListView from '@/views/ZonesListView.vue'
import { fetchZones } from '@/api/zones'
import { useCitySelection } from '@/composables/useCitySelection'

vi.mock('@/api/zones', () => ({
  fetchZones: vi.fn(),
}))

const fetchZonesMock = vi.mocked(fetchZones)
const { resetSelectedCity, setSelectedCity } = useCitySelection()

enableAutoUnmount(afterEach)

function deferred<T>() {
  let resolve!: (value: T) => void
  let reject!: (reason?: unknown) => void
  const promise = new Promise<T>((res, rej) => {
    resolve = res
    reject = rej
  })

  return { promise, resolve, reject }
}

async function flushPromises() {
  await new Promise((resolve) => setTimeout(resolve, 0))
}

describe('ZonesListView', () => {
  beforeEach(() => {
    resetSelectedCity()
  })

  afterEach(() => {
    vi.resetAllMocks()
  })

  it('shows loading first and then renders fetched zones', async () => {
    const pending = deferred<
      Array<{
        id: number
        name: string
        city: 'helsinki' | 'espoo' | 'vantaa'
        type: string
        status: string
        hourlyRateEur: number
        latitude: number
        longitude: number
      }>
    >()
    fetchZonesMock.mockReturnValueOnce(pending.promise)

    const wrapper = mount(ZonesListView, {
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    })

    await flushPromises()

    expect(wrapper.text()).toContain('Loading zones…')
    expect(fetchZonesMock).toHaveBeenCalledWith('helsinki')

    pending.resolve([
      {
        id: 1,
        name: 'Kamppi Center',
        city: 'helsinki',
        type: 'commercial',
        status: 'active',
        hourlyRateEur: 4.5,
        latitude: 60.1685,
        longitude: 24.9318,
      },
    ])
    await flushPromises()

    expect(wrapper.text()).toContain('Kamppi Center')
    expect(wrapper.text()).not.toContain('Loading zones…')
  })

  it('shows an empty state when there are no zones', async () => {
    fetchZonesMock.mockResolvedValueOnce([])

    const wrapper = mount(ZonesListView, {
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    })

    await flushPromises()

    expect(wrapper.text()).toContain('No zones found')
  })

  it('shows the request error when loading fails', async () => {
    fetchZonesMock.mockRejectedValueOnce(new Error('Backend unavailable'))

    const wrapper = mount(ZonesListView, {
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    })

    await flushPromises()

    expect(wrapper.text()).toContain('Backend unavailable')
  })

  it('filters visible zones when a type chip is clicked', async () => {
    fetchZonesMock.mockResolvedValueOnce([
      {
        id: 1,
        name: 'Kamppi Center',
        city: 'helsinki',
        type: 'commercial',
        status: 'active',
        hourlyRateEur: 4.5,
        latitude: 60.1685,
        longitude: 24.9318,
      },
      {
        id: 2,
        name: 'Esplanadi Park',
        city: 'helsinki',
        type: 'street',
        status: 'active',
        hourlyRateEur: 3.5,
        latitude: 60.167,
        longitude: 24.9475,
      },
    ])

    const wrapper = mount(ZonesListView, {
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    })

    await flushPromises()
    await wrapper.get('button.type-filter').trigger('click')

    expect(wrapper.text()).toContain('Filtered by type')
    expect(wrapper.text()).toContain('Esplanadi Park')
    expect(wrapper.text()).not.toContain('Kamppi Center')
  })

  it('reloads zones when the selected city changes', async () => {
    fetchZonesMock
      .mockResolvedValueOnce([
        {
          id: 1,
          name: 'Kamppi Center',
          city: 'helsinki',
          type: 'commercial',
          status: 'active',
          hourlyRateEur: 4.5,
          latitude: 60.1685,
          longitude: 24.9318,
        },
      ])
      .mockResolvedValueOnce([
        {
          id: 8,
          name: 'Sello Parkki',
          city: 'espoo',
          type: 'commercial',
          status: 'active',
          hourlyRateEur: 3.1,
          latitude: 60.2189,
          longitude: 24.8127,
        },
      ])

    const wrapper = mount(ZonesListView, {
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    })

    await flushPromises()
    setSelectedCity('espoo')
    await flushPromises()
    await flushPromises()

    expect(fetchZonesMock).toHaveBeenNthCalledWith(1, 'helsinki')
    expect(fetchZonesMock).toHaveBeenNthCalledWith(2, 'espoo')
    expect(wrapper.text()).toContain('Sello Parkki')
  })
})
