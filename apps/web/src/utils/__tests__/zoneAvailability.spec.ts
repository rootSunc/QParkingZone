import { describe, expect, it } from 'vitest'
import { getZoneAvailability } from '@/utils/zoneAvailability'

describe('zoneAvailability', () => {
  it('marks a zone as open during same-day opening hours', () => {
    const availability = getZoneAvailability(
      'active',
      {
        weekdays: '06:00-23:30',
        weekends: '08:00-23:30',
      },
      new Date('2025-01-13T10:00:00+02:00'),
    )

    expect(availability).toEqual({
      state: 'open',
      badge: 'Open now',
      detail: 'Closes at 23:30',
      schedule: '06:00-23:30',
    })
  })

  it('keeps overnight hours open after midnight', () => {
    const availability = getZoneAvailability(
      'active',
      {
        weekdays: '04:30-01:00',
        weekends: '05:30-01:00',
      },
      new Date('2025-01-14T00:30:00+02:00'),
    )

    expect(availability).toEqual({
      state: 'open',
      badge: 'Open now',
      detail: 'Closes at 01:00',
      schedule: '04:30-01:00',
    })
  })

  it('reports the next opening time when the zone is closed today', () => {
    const availability = getZoneAvailability(
      'active',
      {
        weekdays: '06:00-23:30',
        weekends: 'Closed',
      },
      new Date('2025-01-12T09:00:00+02:00'),
    )

    expect(availability).toEqual({
      state: 'closed',
      badge: 'Closed now',
      detail: 'Opens tomorrow at 06:00',
      schedule: 'Closed',
    })
  })

  it('returns inactive when the zone itself is unavailable', () => {
    const availability = getZoneAvailability(
      'inactive',
      {
        weekdays: '00:00-23:59',
        weekends: '00:00-23:59',
      },
      new Date('2025-01-13T10:00:00+02:00'),
    )

    expect(availability).toEqual({
      state: 'inactive',
      badge: 'Inactive',
      detail: 'Temporarily unavailable',
      schedule: 'Closed',
    })
  })
})
