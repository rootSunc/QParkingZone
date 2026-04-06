import { enableAutoUnmount, RouterLinkStub, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import ZoneDetailView from '@/views/ZoneDetailView.vue'
import { fetchZone } from '@/api/zones'
import { useCitySelection } from '@/composables/useCitySelection'

const mapRemove = vi.fn()
const mapInvalidateSize = vi.fn()
let mapInstance: {
  setView: ReturnType<typeof vi.fn>
  remove: ReturnType<typeof vi.fn>
  invalidateSize: ReturnType<typeof vi.fn>
}

const mapSetView = vi.fn(() => {
  return mapInstance
})
const tileLayerAddTo = vi.fn()
const markerAddTo = vi.fn()

vi.mock('@/api/zones', () => ({
  fetchZone: vi.fn(),
}))

vi.mock('leaflet', () => ({
  default: {
    map: vi.fn(() => mapInstance),
    tileLayer: vi.fn(() => ({
      addTo: tileLayerAddTo,
    })),
    marker: vi.fn(() => ({
      addTo: markerAddTo,
    })),
  },
}))

const fetchZoneMock = vi.mocked(fetchZone)
const { resetSelectedCity } = useCitySelection()

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

describe('ZoneDetailView', () => {
  beforeEach(() => {
    resetSelectedCity()
    mapInstance = {
      setView: mapSetView,
      remove: mapRemove,
      invalidateSize: mapInvalidateSize,
    }

    vi.stubGlobal('requestAnimationFrame', (callback: FrameRequestCallback) => {
      callback(0)
      return 1
    })
  })

  afterEach(() => {
    vi.resetAllMocks()
    vi.unstubAllGlobals()
  })

  it('shows loading first and then renders zone details', async () => {
    const pending = deferred<{
      id: number
      name: string
      city: 'helsinki' | 'espoo' | 'vantaa'
      type: string
      status: string
      description: string
      maxCapacity: number
      hourlyRateEur: number
      latitude: number
      longitude: number
      amenities: string[]
      openingHours: { weekdays: string; weekends: string }
    }>()
    fetchZoneMock.mockReturnValueOnce(pending.promise)

    const wrapper = mount(ZoneDetailView, {
      props: { id: '1' },
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    })

    expect(wrapper.text()).toContain('Loading zone details…')

    pending.resolve({
      id: 1,
      name: 'Kamppi Center',
      city: 'helsinki',
      type: 'commercial',
      status: 'active',
      description: 'Underground parking facility',
      maxCapacity: 450,
      hourlyRateEur: 4.5,
      latitude: 60.1685,
      longitude: 24.9318,
      amenities: ['EV Charging'],
      openingHours: {
        weekdays: '06:00-23:00',
        weekends: '08:00-23:00',
      },
    })
    await flushPromises()
    await flushPromises()

    expect(wrapper.text()).toContain('Kamppi Center')
    expect(wrapper.text()).toContain('Underground parking facility')
    expect(wrapper.text()).toContain('EV Charging')
    expect(mapSetView).toHaveBeenCalled()
  })

  it('shows the request error when loading fails', async () => {
    fetchZoneMock.mockRejectedValueOnce(new Error('Zone not found'))

    const wrapper = mount(ZoneDetailView, {
      props: { id: '999' },
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    })

    await flushPromises()

    expect(wrapper.text()).toContain('Zone not found')
  })

  it('removes the leaflet map when unmounted', async () => {
    fetchZoneMock.mockResolvedValueOnce({
      id: 1,
      name: 'Kamppi Center',
      city: 'helsinki',
      type: 'commercial',
      status: 'active',
      description: 'Underground parking facility',
      maxCapacity: 450,
      hourlyRateEur: 4.5,
      latitude: 60.1685,
      longitude: 24.9318,
      amenities: ['EV Charging'],
      openingHours: {
        weekdays: '06:00-23:00',
        weekends: '08:00-23:00',
      },
    })

    const wrapper = mount(ZoneDetailView, {
      props: { id: '1' },
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
        },
      },
    })

    await flushPromises()
    await flushPromises()
    wrapper.unmount()

    expect(mapRemove).toHaveBeenCalled()
  })
})
