<?php
declare(strict_types=1);

namespace ParkingZones\Domain;

use DateTimeImmutable;
use DateTimeZone;

final class OpeningHoursEvaluator
{
    public const ZONE_TIME_ZONE = 'Europe/Helsinki';

    public function __construct(
        private readonly ?DateTimeImmutable $currentTime = null
    ) {
    }

    public function isOpenNow(string $status, array $openingHours): bool
    {
        return $this->evaluate($status, $openingHours)['state'] === 'open';
    }

    /**
     * @param array{weekdays?: string, weekends?: string} $openingHours
     * @return array{state: string, badge: string, detail: string, schedule: string, isOpen: bool}
     */
    public function evaluate(string $status, array $openingHours): array
    {
        if ($status !== 'active') {
            return [
                'state' => 'inactive',
                'badge' => 'Inactive',
                'detail' => 'Temporarily unavailable',
                'schedule' => 'Closed',
                'isOpen' => false,
            ];
        }

        $clock = $this->getZoneClock();
        $scheduleKey = $this->getScheduleKey($clock['day']);
        $schedule = is_string($openingHours[$scheduleKey] ?? null) ? $openingHours[$scheduleKey] : '';
        $ranges = $this->buildRelativeRanges($openingHours, $clock['day']);

        foreach ($ranges as $range) {
            if ($range['start'] <= $clock['minutes'] && $clock['minutes'] < $range['end']) {
                return [
                    'state' => 'open',
                    'badge' => 'Open now',
                    'detail' => $this->describeBoundary('Closes', $range['end']),
                    'schedule' => $schedule,
                    'isOpen' => true,
                ];
            }
        }

        foreach ($ranges as $range) {
            if ($range['start'] > $clock['minutes']) {
                return [
                    'state' => 'closed',
                    'badge' => 'Closed now',
                    'detail' => $this->describeBoundary('Opens', $range['start']),
                    'schedule' => $schedule,
                    'isOpen' => false,
                ];
            }
        }

        return [
            'state' => 'closed',
            'badge' => 'Closed now',
            'detail' => 'Closed until schedule updates',
            'schedule' => $schedule,
            'isOpen' => false,
        ];
    }

    /**
     * @return array{day: int, minutes: int}
     */
    public function getZoneClock(): array
    {
        $now = ($this->currentTime ?? new DateTimeImmutable('now'))
            ->setTimezone(new DateTimeZone(self::ZONE_TIME_ZONE));

        return [
            'day' => (int) $now->format('w'),
            'minutes' => ((int) $now->format('G') * 60) + (int) $now->format('i'),
        ];
    }

    /**
     * @param array{weekdays?: string, weekends?: string} $openingHours
     * @return list<array{start: int, end: int}>
     */
    private function buildRelativeRanges(array $openingHours, int $day): array
    {
        $previousDay = ($day + 6) % 7;
        $nextDay = ($day + 1) % 7;
        $ranges = [];

        foreach ($this->parseSchedule($openingHours[$this->getScheduleKey($previousDay)] ?? '') as $range) {
            if ($range['wraps']) {
                $ranges[] = [
                    'start' => $range['start'] - 1440,
                    'end' => $range['end'],
                ];
            }
        }

        foreach ($this->parseSchedule($openingHours[$this->getScheduleKey($day)] ?? '') as $range) {
            $ranges[] = [
                'start' => $range['start'],
                'end' => $range['wraps'] ? 1440 + $range['end'] : $range['end'],
            ];
        }

        foreach ($this->parseSchedule($openingHours[$this->getScheduleKey($nextDay)] ?? '') as $range) {
            $ranges[] = [
                'start' => 1440 + $range['start'],
                'end' => $range['wraps'] ? 2880 + $range['end'] : 1440 + $range['end'],
            ];
        }

        usort($ranges, fn (array $left, array $right): int => $left['start'] <=> $right['start']);

        return $ranges;
    }

    /**
     * @return list<array{start: int, end: int, wraps: bool}>
     */
    private function parseSchedule(string $schedule): array
    {
        $normalized = strtolower(trim($schedule));

        if ($normalized === '' || $normalized === 'closed') {
            return [];
        }

        $ranges = [];

        foreach (explode(',', $schedule) as $segment) {
            $match = [];

            if (!preg_match('/^(\d{2}):(\d{2})-(\d{2}):(\d{2})$/', trim($segment), $match)) {
                continue;
            }

            $start = ((int) $match[1] * 60) + (int) $match[2];
            $end = ((int) $match[3] * 60) + (int) $match[4];

            if ($start === $end) {
                $ranges[] = [
                    'start' => $start,
                    'end' => 1440,
                    'wraps' => false,
                ];

                continue;
            }

            $ranges[] = [
                'start' => $start,
                'end' => $end,
                'wraps' => $end < $start,
            ];
        }

        return $ranges;
    }

    private function getScheduleKey(int $day): string
    {
        return in_array($day, [0, 6], true) ? 'weekends' : 'weekdays';
    }

    private function describeBoundary(string $prefix, int $minutes): string
    {
        if ($minutes >= 1440) {
            return sprintf('%s tomorrow at %s', $prefix, $this->formatClock($minutes));
        }

        if ($minutes < 0) {
            return sprintf('%s yesterday at %s', $prefix, $this->formatClock($minutes));
        }

        return sprintf('%s at %s', $prefix, $this->formatClock($minutes));
    }

    private function formatClock(int $minutes): string
    {
        $normalized = (($minutes % 1440) + 1440) % 1440;
        $hours = intdiv($normalized, 60);
        $mins = $normalized % 60;

        return sprintf('%02d:%02d', $hours, $mins);
    }
}
