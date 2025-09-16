import { enableAutoUnmount, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { ref } from 'vue'
import ZoneDetailView from '@/views/ZoneDetailView.vue'
import { fetchZone } from '@/api/zones'

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

vi.mock('@/composables/useCurrentMinute', () => ({
  useCurrentMinute: () => ref(new Date('2025-01-13T10:00:00+02:00')),
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
  await Promise.resolve()
  await Promise.resolve()
}

async function mountView(initialQuery: Record<string, string> = { city: 'helsinki' }) {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/zones/:id',
        name: 'zone-detail',
        component: ZoneDetailView,
        props: true,
      },
      {
        path: '/',
        name: 'zones',
        component: {
          template: '<div />',
        },
      },
    ],
  })

  await router.push({
    name: 'zone-detail',
    params: { id: '1' },
    query: initialQuery,
  })
  await router.isReady()

  return {
    router,
    wrapper: mount(ZoneDetailView, {
      props: { id: '1' },
      global: {
        plugins: [router],
      },
    }),
  }
}

describe('ZoneDetailView', () => {
  beforeEach(() => {
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

    const { wrapper } = await mountView()

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

    expect(fetchZoneMock).toHaveBeenCalledWith('1', expect.any(AbortSignal))
    expect(wrapper.text()).toContain('Kamppi Center')
    expect(wrapper.text()).toContain('Underground parking facility')
    expect(wrapper.text()).toContain('EV Charging')
    expect(wrapper.text()).toContain('Open now')
    expect(wrapper.text()).toContain('Closes at 23:00')
  })

  it('shows the request error when loading fails', async () => {
    fetchZoneMock.mockRejectedValueOnce(new Error('Zone not found'))

    const { wrapper } = await mountView()

    await flushPromises()

    expect(wrapper.text()).toContain('Zone not found')
  })

  it('syncs the route city query to the loaded zone city', async () => {
    fetchZoneMock.mockResolvedValueOnce({
      id: 1,
      name: 'Tapiola AINOA Parking',
      city: 'espoo',
      type: 'commercial',
      status: 'active',
      description: 'Retail garage',
      maxCapacity: 690,
      hourlyRateEur: 3.6,
      latitude: 60.1782,
      longitude: 24.8047,
      amenities: ['EV Charging'],
      openingHours: {
        weekdays: '06:00-23:30',
        weekends: '08:00-23:30',
      },
    })

    const { wrapper } = await mountView({ city: 'helsinki', q: 'aino' })

    await flushPromises()
    await flushPromises()

    expect(wrapper.get('a.back-link').attributes('href')).toContain('city=espoo')
    expect(wrapper.get('a.back-link').attributes('href')).toContain('q=aino')
  })

  it('can unmount cleanly after details load', async () => {
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

    const { wrapper } = await mountView()

    await flushPromises()
    await flushPromises()
    wrapper.unmount()

    expect(wrapper.exists()).toBe(false)
  })
})
