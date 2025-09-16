import type { OpeningHours } from '@/api/zones'

export const zoneTimeZone = 'Europe/Helsinki'

type AvailabilityState = 'open' | 'closed' | 'inactive'
type HoursKey = keyof OpeningHours

interface TimeRange {
  start: number
  end: number
  wraps: boolean
}

interface RelativeRange {
  start: number
  end: number
}

interface ZoneClock {
  day: number
  minutes: number
}

export interface ZoneAvailability {
  state: AvailabilityState
  badge: string
  detail: string
  schedule: string
}

const clockFormatter = new Intl.DateTimeFormat('en-US', {
  timeZone: zoneTimeZone,
  weekday: 'short',
  hour: '2-digit',
  minute: '2-digit',
  hour12: false,
})

const weekdayIndex = {
  Sun: 0,
  Mon: 1,
  Tue: 2,
  Wed: 3,
  Thu: 4,
  Fri: 5,
  Sat: 6,
} as const

function isWeekend(day: number): boolean {
  return day === 0 || day === 6
}

function getScheduleKey(day: number): HoursKey {
  return isWeekend(day) ? 'weekends' : 'weekdays'
}

function formatClock(minutes: number): string {
  const normalized = ((minutes % 1440) + 1440) % 1440
  const hours = Math.floor(normalized / 60)
  const mins = normalized % 60

  return `${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}`
}

function describeBoundary(prefix: string, minutes: number): string {
  if (minutes >= 1440) {
    return `${prefix} tomorrow at ${formatClock(minutes)}`
  }

  if (minutes < 0) {
    return `${prefix} yesterday at ${formatClock(minutes)}`
  }

  return `${prefix} at ${formatClock(minutes)}`
}

function parseTimeRange(segment: string): TimeRange | null {
  const match = segment.trim().match(/^(\d{2}):(\d{2})-(\d{2}):(\d{2})$/)

  if (!match) {
    return null
  }

  const start = Number.parseInt(match[1]!, 10) * 60 + Number.parseInt(match[2]!, 10)
  const end = Number.parseInt(match[3]!, 10) * 60 + Number.parseInt(match[4]!, 10)

  if (start === end) {
    return {
      start,
      end: 1440,
      wraps: false,
    }
  }

  return {
    start,
    end,
    wraps: end < start,
  }
}

function parseSchedule(schedule: string): TimeRange[] {
  const normalized = schedule.trim()

  if (normalized === '' || normalized.toLowerCase() === 'closed') {
    return []
  }

  return normalized
    .split(',')
    .map(parseTimeRange)
    .filter((range): range is TimeRange => range !== null)
}

function buildRelativeRanges(openingHours: OpeningHours, day: number): RelativeRange[] {
  const previousDay = (day + 6) % 7
  const nextDay = (day + 1) % 7
  const ranges: RelativeRange[] = []

  for (const range of parseSchedule(openingHours[getScheduleKey(previousDay)])) {
    if (range.wraps) {
      ranges.push({
        start: range.start - 1440,
        end: range.end,
      })
    }
  }

  for (const range of parseSchedule(openingHours[getScheduleKey(day)])) {
    ranges.push({
      start: range.start,
      end: range.wraps ? 1440 + range.end : range.end,
    })
  }

  for (const range of parseSchedule(openingHours[getScheduleKey(nextDay)])) {
    ranges.push({
      start: 1440 + range.start,
      end: range.wraps ? 2880 + range.end : 1440 + range.end,
    })
  }

  return ranges.sort((left, right) => left.start - right.start)
}

function getZoneClock(now: Date): ZoneClock {
  const parts = clockFormatter.formatToParts(now)
  const weekday = parts.find((part) => part.type === 'weekday')?.value
  const hourValue = Number.parseInt(parts.find((part) => part.type === 'hour')?.value ?? '0', 10)
  const minute = parts.find((part) => part.type === 'minute')?.value
  const normalizedHour = hourValue === 24 ? 0 : hourValue

  return {
    day: weekday !== undefined ? weekdayIndex[weekday as keyof typeof weekdayIndex] : 1,
    minutes: normalizedHour * 60 + Number.parseInt(minute ?? '0', 10),
  }
}

export function getZoneAvailability(
  status: string,
  openingHours: OpeningHours,
  now: Date = new Date(),
): ZoneAvailability {
  if (status !== 'active') {
    return {
      state: 'inactive',
      badge: 'Inactive',
      detail: 'Temporarily unavailable',
      schedule: 'Closed',
    }
  }

  const clock = getZoneClock(now)
  const schedule = openingHours[getScheduleKey(clock.day)]
  const ranges = buildRelativeRanges(openingHours, clock.day)
  const activeRange = ranges.find((range) => range.start <= clock.minutes && clock.minutes < range.end)

  if (activeRange) {
    return {
      state: 'open',
      badge: 'Open now',
      detail: describeBoundary('Closes', activeRange.end),
      schedule,
    }
  }

  const nextRange = ranges.find((range) => range.start > clock.minutes)

  if (nextRange) {
    return {
      state: 'closed',
      badge: 'Closed now',
      detail: describeBoundary('Opens', nextRange.start),
      schedule,
    }
  }

  return {
    state: 'closed',
    badge: 'Closed now',
    detail: 'Closed until schedule updates',
    schedule,
  }
}
