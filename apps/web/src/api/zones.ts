import type { CitySlug } from '@/config/cities'

export interface ZoneSummary {
  id: number
  name: string
  city: CitySlug
  type: string
  status: string
  hourlyRateEur: number
  latitude: number
  longitude: number
}

export interface OpeningHours {
  weekdays: string
  weekends: string
}

export interface ZoneDetail extends ZoneSummary {
  description: string
  maxCapacity: number
  amenities: string[]
  openingHours: OpeningHours
}

async function readJson<T>(response: Response): Promise<T> {
  if (!response.ok) {
    const body = await response.json().catch(() => null)
    throw new Error(body?.error ?? `Request failed with ${response.status}`)
  }

  return response.json() as Promise<T>
}

export async function fetchZones(city?: CitySlug): Promise<ZoneSummary[]> {
  const params = new URLSearchParams()

  if (city) {
    params.set('city', city)
  }

  const suffix = params.toString() ? `?${params.toString()}` : ''

  return readJson(await fetch(`/api/zones${suffix}`))
}

export async function fetchZone(id: string | number): Promise<ZoneDetail> {
  return readJson(await fetch(`/api/zones/${id}`))
}
