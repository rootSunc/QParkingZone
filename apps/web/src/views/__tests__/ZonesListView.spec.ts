import { enableAutoUnmount, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { ref } from 'vue'
import ZonesListView from '@/views/ZonesListView.vue'
import { fetchZones, type ZonesPage } from '@/api/zones'

vi.mock('@/api/zones', () => ({
  fetchZones: vi.fn(),
}))

vi.mock('@/composables/useCurrentMinute', () => ({
  useCurrentMinute: () => ref(new Date('2025-01-13T10:00:00+02:00')),
}))

const fetchZonesMock = vi.mocked(fetchZones)

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

function createZonesPage(items: ZonesPage['items'], overrides: Partial<ZonesPage> = {}): ZonesPage {
  return {
    items,
    total: items.length,
    page: 1,
    limit: 4,
    ...overrides,
  }
}

function createZoneSummary(overrides: Partial<ZonesPage['items'][number]> = {}): ZonesPage['items'][number] {
  return {
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
    ...overrides,
  }
}

async function mountView(initialQuery: Record<string, string> = { city: 'helsinki' }) {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/',
        name: 'zones',
        component: ZonesListView,
      },
      {
        path: '/zones/:id',
        name: 'zone-detail',
        component: {
          template: '<div />',
        },
      },
    ],
  })

  await router.push({
    name: 'zones',
    query: initialQuery,
  })
  await router.isReady()

  return {
    router,
    wrapper: mount(ZonesListView, {
      global: {
        plugins: [router],
      },
    }),
  }
}

describe('ZonesListView', () => {
  afterEach(() => {
    vi.resetAllMocks()
  })

  it('shows loading first and then renders fetched zones', async () => {
    const pending = deferred<ZonesPage>()
    fetchZonesMock.mockReturnValueOnce(pending.promise)

    const { wrapper } = await mountView()

    await flushPromises()

    expect(wrapper.text()).toContain('Loading zones…')
    expect(fetchZonesMock).toHaveBeenCalledWith(
      expect.objectContaining({
        city: 'helsinki',
        q: '',
        type: null,
        sort: 'name',
        page: 1,
        limit: 4,
      }),
      expect.any(AbortSignal),
    )

    pending.resolve(
      createZonesPage([
        createZoneSummary(),
      ]),
    )
    await flushPromises()

    expect(wrapper.text()).toContain('Kamppi Center')
    expect(wrapper.text()).not.toContain('Loading zones…')
  })

  it('shows an empty state when there are no zones', async () => {
    fetchZonesMock.mockResolvedValueOnce(createZonesPage([], { total: 0 }))

    const { wrapper } = await mountView()

    await flushPromises()

    expect(wrapper.text()).toContain('No zones found')
  })

  it('shows the request error when loading fails', async () => {
    fetchZonesMock.mockRejectedValueOnce(new Error('Backend unavailable'))

    const { wrapper } = await mountView()

    await flushPromises()

    expect(wrapper.text()).toContain('Backend unavailable')
  })

  it('updates the route query and refetches when a type chip is clicked', async () => {
    fetchZonesMock
      .mockResolvedValueOnce(
        createZonesPage([
          createZoneSummary(),
          createZoneSummary({
            id: 2,
            name: 'Esplanadi Park',
            type: 'street',
            hourlyRateEur: 3.5,
            latitude: 60.167,
            longitude: 24.9475,
            openingHours: {
              weekdays: '08:00-21:00',
              weekends: '10:00-18:00',
            },
          }),
        ]),
      )
      .mockResolvedValueOnce(
        createZonesPage([
          createZoneSummary({
            id: 2,
            name: 'Esplanadi Park',
            type: 'street',
            hourlyRateEur: 3.5,
            latitude: 60.167,
            longitude: 24.9475,
            openingHours: {
              weekdays: '08:00-21:00',
              weekends: '10:00-18:00',
            },
          }),
        ]),
      )

    const { router, wrapper } = await mountView()

    await flushPromises()
    await wrapper.get('button.type-filter').trigger('click')
    await flushPromises()
    await flushPromises()

    expect(router.currentRoute.value.query.type).toBe('commercial')
    expect(fetchZonesMock).toHaveBeenLastCalledWith(
      expect.objectContaining({
        city: 'helsinki',
        type: 'commercial',
        page: 1,
      }),
      expect.any(AbortSignal),
    )
    expect(wrapper.text()).toContain('Filtered by type')
  })

  it('reloads zones when the city query changes', async () => {
    fetchZonesMock
      .mockResolvedValueOnce(
        createZonesPage([
          createZoneSummary(),
        ]),
      )
      .mockResolvedValueOnce(
        createZonesPage([
          createZoneSummary({
            id: 8,
            name: 'Sello Parkki',
            city: 'espoo',
            hourlyRateEur: 3.1,
            latitude: 60.2189,
            longitude: 24.8127,
          }),
        ]),
      )

    const { router, wrapper } = await mountView()

    await flushPromises()
    await router.replace({
      name: 'zones',
      query: { city: 'espoo' },
    })
    await flushPromises()
    await flushPromises()

    expect(fetchZonesMock).toHaveBeenNthCalledWith(
      1,
      expect.objectContaining({ city: 'helsinki' }),
      expect.any(AbortSignal),
    )
    expect(fetchZonesMock).toHaveBeenNthCalledWith(
      2,
      expect.objectContaining({ city: 'espoo' }),
      expect.any(AbortSignal),
    )
    expect(wrapper.text()).toContain('Sello Parkki')
  })

  it('keeps stale responses from overwriting the newest route state', async () => {
    const firstRequest = deferred<ZonesPage>()
    fetchZonesMock
      .mockReturnValueOnce(firstRequest.promise)
      .mockResolvedValueOnce(
        createZonesPage([
          createZoneSummary({
            id: 8,
            name: 'Sello Parkki',
            city: 'espoo',
            hourlyRateEur: 3.1,
            latitude: 60.2189,
            longitude: 24.8127,
          }),
        ]),
      )

    const { router, wrapper } = await mountView()

    await flushPromises()
    await router.replace({
      name: 'zones',
      query: { city: 'espoo' },
    })
    await flushPromises()
    await flushPromises()

    firstRequest.resolve(
      createZonesPage([
        createZoneSummary(),
      ]),
    )
    await flushPromises()

    expect(wrapper.text()).toContain('Sello Parkki')
    expect(wrapper.text()).not.toContain('Kamppi Center')
  })

  it('updates the page query when pagination controls are used', async () => {
    fetchZonesMock
      .mockResolvedValueOnce(
        createZonesPage(
          [
            createZoneSummary(),
            createZoneSummary({
              id: 2,
              name: 'Esplanadi Park',
              type: 'street',
              hourlyRateEur: 3.5,
              latitude: 60.167,
              longitude: 24.9475,
              openingHours: {
                weekdays: '08:00-21:00',
                weekends: '10:00-18:00',
              },
            }),
            createZoneSummary({
              id: 3,
              name: 'Pasila Tripla Parking',
              hourlyRateEur: 3.2,
              latitude: 60.1989,
              longitude: 24.933,
            }),
            createZoneSummary({
              id: 4,
              name: 'Hakaniemi Market Square',
              type: 'street',
              hourlyRateEur: 3.4,
              latitude: 60.1788,
              longitude: 24.9506,
              openingHours: {
                weekdays: '07:00-21:00',
                weekends: '08:00-18:00',
              },
            }),
          ],
          { total: 5, page: 1, limit: 4 },
        ),
      )
      .mockResolvedValueOnce(
        createZonesPage(
          [
            createZoneSummary({
              id: 5,
              name: 'Herttoniemi Center Parking',
              status: 'inactive',
              hourlyRateEur: 2.4,
              latitude: 60.1942,
              longitude: 25.0285,
              openingHours: {
                weekdays: 'Closed',
                weekends: 'Closed',
              },
            }),
          ],
          { total: 5, page: 2, limit: 4 },
        ),
      )

    const { router, wrapper } = await mountView()

    await flushPromises()
    await wrapper.get('button.pagination-button:last-child').trigger('click')
    await flushPromises()
    await flushPromises()

    expect(router.currentRoute.value.query.page).toBe('2')
    expect(fetchZonesMock).toHaveBeenLastCalledWith(
      expect.objectContaining({
        city: 'helsinki',
        page: 2,
      }),
      expect.any(AbortSignal),
    )
    expect(wrapper.text()).toContain('Herttoniemi Center Parking')
  })
})
