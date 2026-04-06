import { afterEach, describe, expect, it, vi } from 'vitest'
import { fetchZone, fetchZones } from '@/api/zones'

describe('zones api', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('returns parsed zone summaries', async () => {
    const mockZones = {
      items: [
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
      ],
      total: 1,
      page: 1,
      limit: 4,
    }

    vi.spyOn(global, 'fetch').mockResolvedValue({
      ok: true,
      json: async () => mockZones,
    } as Response)

    const result = await fetchZones({
      city: 'helsinki',
      q: 'kamppi',
      type: 'commercial',
      sort: 'price_desc',
      page: 2,
      limit: 4,
    })

    expect(fetch).toHaveBeenCalledWith(
      '/api/zones?city=helsinki&q=kamppi&type=commercial&sort=price_desc&page=2&limit=4',
      { signal: undefined },
    )
    expect(result).toEqual(mockZones)
  })

  it('returns parsed zone details', async () => {
    const mockZone = {
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
    }

    vi.spyOn(global, 'fetch').mockResolvedValue({
      ok: true,
      json: async () => mockZone,
    } as Response)

    const result = await fetchZone(1)

    expect(fetch).toHaveBeenCalledWith('/api/zones/1', { signal: undefined })
    expect(result).toEqual(mockZone)
  })

  it('throws when the request fails', async () => {
    vi.spyOn(global, 'fetch').mockResolvedValue({
      ok: false,
      json: async () => ({ error: 'Failed to fetch zones' }),
    } as Response)

    await expect(fetchZones({ city: 'helsinki' })).rejects.toThrow('Failed to fetch zones')
  })
})
