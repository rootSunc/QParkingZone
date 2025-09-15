export const cityOptions = [
  { slug: 'helsinki', label: 'Helsinki' },
  { slug: 'espoo', label: 'Espoo' },
  { slug: 'vantaa', label: 'Vantaa' },
] as const

export type CitySlug = (typeof cityOptions)[number]['slug']

export const defaultCitySlug: CitySlug = 'helsinki'

export function isCitySlug(value: string): value is CitySlug {
  return cityOptions.some((option) => option.slug === value)
}

export function getCityLabel(slug: CitySlug): string {
  return cityOptions.find((option) => option.slug === slug)?.label ?? 'Helsinki'
}
