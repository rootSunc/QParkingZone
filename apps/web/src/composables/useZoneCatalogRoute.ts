import type { LocationQuery, LocationQueryRaw, LocationQueryValue } from 'vue-router'
import { defaultCitySlug, isCitySlug, type CitySlug } from '@/config/cities'

export const defaultZonePageSize = 12
export const zoneSortOptions = ['name', 'price_desc', 'price_asc', 'distance_asc'] as const

export type ZoneSort = (typeof zoneSortOptions)[number]

export interface ZoneCatalogQueryState {
  city: CitySlug
  q: string
  type: string | null
  openNow: boolean
  lat: number | null
  lng: number | null
  radius: number | null
  amenities: string[]
  sort: ZoneSort
  page: number
  limit: number
}

const zoneCatalogKeys = new Set(['city', 'q', 'type', 'open_now', 'lat', 'lng', 'radius', 'amenities', 'sort', 'page', 'limit'])

function readQueryValue(value: LocationQueryValue | LocationQueryValue[] | undefined): string | null {
  if (Array.isArray(value)) {
    return typeof value[0] === 'string' ? value[0] : null
  }

  return typeof value === 'string' ? value : null
}

function readPositiveInteger(value: string | null, fallback: number): number {
  if (value === null) {
    return fallback
  }

  const parsed = Number.parseInt(value, 10)

  return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback
}

function readBoolean(value: string | null): boolean {
  if (value === null) {
    return false
  }

  return ['1', 'true', 'yes', 'on'].includes(value.toLowerCase())
}

function readCoordinate(value: string | null): number | null {
  if (value === null) {
    return null
  }

  const parsed = Number.parseFloat(value)

  return Number.isFinite(parsed) ? parsed : null
}

function formatCoordinate(value: number): string {
  return value.toFixed(5)
}

function readArrayString(value: LocationQueryValue | LocationQueryValue[] | undefined): string[] {
  if (!value) return [];
  if (Array.isArray(value)) {
    return value.map(v => String(v)).filter(Boolean);
  }
  return String(value).split(',').map(s => s.trim()).filter(Boolean);
}

export function parseZoneCatalogQuery(query: LocationQuery): ZoneCatalogQueryState {
  const cityValue = readQueryValue(query.city)
  const sortValue = readQueryValue(query.sort)
  const q = readQueryValue(query.q)?.trim() ?? ''
  const type = readQueryValue(query.type)?.trim() ?? null
  const openNow = readBoolean(readQueryValue(query.open_now))
  const lat = readCoordinate(readQueryValue(query.lat))
  const lng = readCoordinate(readQueryValue(query.lng))
  const radius = readCoordinate(readQueryValue(query.radius))
  const amenities = readArrayString(query.amenities)
  const hasCoordinates = lat !== null && lng !== null
  const page = readPositiveInteger(readQueryValue(query.page), 1)
  const limit = readPositiveInteger(readQueryValue(query.limit), defaultZonePageSize)
  const parsedSort = zoneSortOptions.includes(sortValue as ZoneSort) ? (sortValue as ZoneSort) : 'name'

  return {
    city: cityValue !== null && isCitySlug(cityValue) ? cityValue : defaultCitySlug,
    q,
    type: type === '' ? null : type,
    openNow,
    lat: hasCoordinates ? lat : null,
    lng: hasCoordinates ? lng : null,
    radius: hasCoordinates && radius !== null ? radius : null,
    amenities,
    sort: parsedSort === 'distance_asc' && !hasCoordinates ? 'name' : parsedSort,
    page,
    limit,
  }
}

export function updateZoneCatalogQuery(
  query: LocationQuery,
  patch: Partial<ZoneCatalogQueryState>,
): LocationQueryRaw {
  const nextState = {
    ...parseZoneCatalogQuery(query),
    ...patch,
  }
  const hasCoordinates = nextState.lat !== null && nextState.lng !== null
  const nextSort = nextState.sort === 'distance_asc' && !hasCoordinates ? 'name' : nextState.sort
  const nextQuery: LocationQueryRaw = {}

  for (const [key, value] of Object.entries(query)) {
    if (!zoneCatalogKeys.has(key)) {
      nextQuery[key] = value
    }
  }

  nextQuery.city = nextState.city

  if (nextState.q !== '') {
    nextQuery.q = nextState.q
  }

  if (nextState.type !== null) {
    nextQuery.type = nextState.type
  }

  if (nextState.openNow) {
    nextQuery.open_now = '1'
  }

  if (nextState.lat !== null && nextState.lng !== null) {
    nextQuery.lat = formatCoordinate(nextState.lat)
    nextQuery.lng = formatCoordinate(nextState.lng)
  }

  if (nextState.radius !== null) {
    nextQuery.radius = String(nextState.radius)
  }

  if (nextState.amenities.length > 0) {
    nextQuery.amenities = nextState.amenities.join(',')
  }

  if (nextSort !== 'name') {
    nextQuery.sort = nextSort
  }

  if (nextState.page > 1) {
    nextQuery.page = String(nextState.page)
  }

  if (nextState.limit !== defaultZonePageSize) {
    nextQuery.limit = String(nextState.limit)
  }

  return nextQuery
}
