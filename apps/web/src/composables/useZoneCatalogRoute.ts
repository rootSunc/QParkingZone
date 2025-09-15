import type { LocationQuery, LocationQueryRaw, LocationQueryValue } from 'vue-router'
import { defaultCitySlug, isCitySlug, type CitySlug } from '@/config/cities'

export const defaultZonePageSize = 4
export const zoneSortOptions = ['name', 'price_desc', 'price_asc'] as const

export type ZoneSort = (typeof zoneSortOptions)[number]

export interface ZoneCatalogQueryState {
  city: CitySlug
  q: string
  type: string | null
  sort: ZoneSort
  page: number
  limit: number
}

const zoneCatalogKeys = new Set(['city', 'q', 'type', 'sort', 'page', 'limit'])

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

export function parseZoneCatalogQuery(query: LocationQuery): ZoneCatalogQueryState {
  const cityValue = readQueryValue(query.city)
  const sortValue = readQueryValue(query.sort)
  const q = readQueryValue(query.q)?.trim() ?? ''
  const type = readQueryValue(query.type)?.trim() ?? null
  const page = readPositiveInteger(readQueryValue(query.page), 1)
  const limit = readPositiveInteger(readQueryValue(query.limit), defaultZonePageSize)

  return {
    city: cityValue !== null && isCitySlug(cityValue) ? cityValue : defaultCitySlug,
    q,
    type: type === '' ? null : type,
    sort: zoneSortOptions.includes(sortValue as ZoneSort) ? (sortValue as ZoneSort) : 'name',
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

  if (nextState.sort !== 'name') {
    nextQuery.sort = nextState.sort
  }

  if (nextState.page > 1) {
    nextQuery.page = String(nextState.page)
  }

  if (nextState.limit !== defaultZonePageSize) {
    nextQuery.limit = String(nextState.limit)
  }

  return nextQuery
}
