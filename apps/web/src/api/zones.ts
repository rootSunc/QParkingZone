import type { CitySlug } from '@/config/cities'
import type { ZoneSort } from '@/composables/useZoneCatalogRoute'

export interface ZoneSummary {
  id: number
  name: string
  city: CitySlug
  type: string
  status: string
  hourlyRateEur: number
  latitude: number
  longitude: number
  openingHours: OpeningHours
}

export interface OpeningHours {
  weekdays: string
  weekends: string
}

export interface ZoneDetail extends ZoneSummary {
  description: string
  maxCapacity: number
  amenities: string[]
}

export interface ZonesPage {
  items: ZoneSummary[]
  total: number
  page: number
  limit: number
}

export interface FetchZonesParams {
  city: CitySlug
  q?: string
  type?: string | null
  status?: string
  sort?: ZoneSort
  page?: number
  limit?: number
}

async function readJson<T>(response: Response): Promise<T> {
  if (!response.ok) {
    const body = await response.json().catch(() => null)
    throw new Error(body?.error ?? `Request failed with ${response.status}`)
  }

  return response.json() as Promise<T>
}

export async function fetchZones(
  { city, q, type, status, sort, page, limit }: FetchZonesParams,
  signal?: AbortSignal,
): Promise<ZonesPage> {
  const params = new URLSearchParams()

  params.set('city', city)

  if (q && q.trim() !== '') {
    params.set('q', q.trim())
  }

  if (type) {
    params.set('type', type)
  }

  if (status) {
    params.set('status', status)
  }

  if (sort && sort !== 'name') {
    params.set('sort', sort)
  }

  if (page && page > 1) {
    params.set('page', String(page))
  }

  if (limit) {
    params.set('limit', String(limit))
  }

  const suffix = params.toString() ? `?${params.toString()}` : ''

  return readJson(await fetch(`/api/zones${suffix}`, { signal }))
}

export async function fetchZone(id: string | number, signal?: AbortSignal): Promise<ZoneDetail> {
  return readJson(await fetch(`/api/zones/${id}`, { signal }))
}
