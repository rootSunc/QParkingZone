import { computed } from 'vue'
import type { LocationQuery } from 'vue-router'
import { cityOptions, getCityLabel } from '@/config/cities'
import { parseZoneCatalogQuery } from '@/composables/useZoneCatalogRoute'

export function useCitySelection(querySource: () => LocationQuery) {
  const selectedCity = computed(() => {
    return parseZoneCatalogQuery(querySource()).city
  })

  const selectedCityLabel = computed(() => {
    return getCityLabel(selectedCity.value)
  })

  return {
    cityOptions,
    selectedCity,
    selectedCityLabel,
  }
}
