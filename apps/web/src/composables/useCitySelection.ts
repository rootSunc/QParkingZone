import { computed, ref } from 'vue'
import { cityOptions, defaultCitySlug, getCityLabel, type CitySlug } from '@/config/cities'

const selectedCity = ref<CitySlug>(defaultCitySlug)

export function useCitySelection() {
  const selectedCityLabel = computed(() => {
    return getCityLabel(selectedCity.value)
  })

  function setSelectedCity(nextCity: CitySlug) {
    selectedCity.value = nextCity
  }

  function resetSelectedCity() {
    selectedCity.value = defaultCitySlug
  }

  return {
    cityOptions,
    selectedCity,
    selectedCityLabel,
    setSelectedCity,
    resetSelectedCity,
  }
}
