import { expect, test, type Page } from '@playwright/test'

const zoneSummary = {
  id: 1,
  name: 'Kamppi Center',
  city: 'helsinki',
  type: 'commercial',
  status: 'active',
  hourlyRateEur: 4.8,
  latitude: 60.1685,
  longitude: 24.9318,
  amenities: ['EV Charging', 'Indoor Parking'],
  openingHours: {
    weekdays: '00:00-23:59',
    weekends: '00:00-23:59',
  },
}

const zoneDetail = {
  ...zoneSummary,
  description:
    'Multi-level city-center garage connected to Kamppi shopping complex, with direct pedestrian access to the metro and bus terminal.',
  maxCapacity: 520,
}

async function mockZonesApi(page: Page) {
  await page.route('**/api/zones/1', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify(zoneDetail),
    })
  })

  await page.route('**/api/zones/facets?**', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({
        city: 'helsinki',
        types: [{ value: 'commercial', count: 1 }],
        statuses: [{ value: 'active', count: 1 }],
        amenities: [
          { value: 'EV Charging', count: 1 },
          { value: 'Indoor Parking', count: 1 },
        ],
      }),
    })
  })

  await page.route('**/api/zones?**', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({
        items: [zoneSummary],
        total: 1,
        page: 1,
        limit: 12,
      }),
    })
  })

  await page.route('**://*.tile.openstreetmap.org/**', async (route) => {
    await route.fulfill({ status: 204 })
  })
}

test('searches zones and opens a detail page', async ({ page }) => {
  await mockZonesApi(page)

  await page.goto('/#/?city=helsinki')

  await expect(page.getByRole('heading', { name: /Simple parking/i })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Kamppi Center' }).first()).toBeVisible()

  await page.getByPlaceholder('Search zones...').fill('kamppi')
  await expect(page).toHaveURL(/q=kamppi/)

  await page.getByRole('link', { name: 'Open details' }).click()

  await expect(page).toHaveURL(/#\/zones\/1/)
  await expect(page.getByRole('heading', { name: 'Kamppi Center' })).toBeVisible()
  await expect(page.getByText('EV Charging')).toBeVisible()
})
