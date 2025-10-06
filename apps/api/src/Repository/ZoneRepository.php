<?php
declare(strict_types=1);

namespace ParkingZones\Repository;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

final class ZoneRepository
{
    private const ZONE_TIME_ZONE = 'Europe/Helsinki';

    public function __construct(
        private readonly PDO $pdo,
        private readonly ?DateTimeImmutable $currentTime = null
    ) {
    }

    public function fetchAllSummaries(ZoneSummaryQuery $query): array
    {
        [$whereClause, $params] = $this->buildSummaryFilters($query);
        $offset = ($query->page - 1) * $query->limit;

        if (!$query->requiresInMemoryFilteringOrSorting()) {
            return [
                'items' => $this->fetchSummaryRows(
                    $whereClause,
                    $params,
                    $this->resolveStaticOrderBy($query->sort),
                    $query->limit,
                    $offset,
                    $query
                ),
                'total' => $this->countSummaries($whereClause, $params),
                'page' => $query->page,
                'limit' => $query->limit,
            ];
        }

        $items = $this->fetchSummaryRows($whereClause, $params, null, null, null, $query);

        if ($query->openNow) {
            $items = array_values(array_filter($items, fn (array $item): bool => $this->isZoneOpenNow($item)));
        }

        if ($query->radius !== null) {
            $items = array_values(array_filter($items, fn (array $item): bool => isset($item['distanceKm']) && $item['distanceKm'] <= $query->radius));
        }

        $this->sortSummaries($items, $query->sort, $query->latitude, $query->longitude);
        $total = count($items);
        $items = array_slice($items, $offset, $query->limit);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $query->page,
            'limit' => $query->limit,
        ];
    }

    public function countZones(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM zones')->fetchColumn();
    }

    public function fetchDetailById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                id,
                name,
                city,
                type,
                status,
                description,
                max_capacity AS maxCapacity,
                hourly_rate_eur AS hourlyRateEur,
                latitude,
                longitude,
                amenities,
                opening_hours AS openingHours
            FROM zones
            WHERE id = :id
        ");

        $stmt->execute(['id' => $id]);
        $zone = $stmt->fetch();

        if ($zone === false) {
            return null;
        }

        $zone['amenities'] = $this->decodeAmenities($zone['amenities']);
        $zone['openingHours'] = $this->decodeOpeningHours($zone['openingHours']);

        return $zone;
    }

    private function fetchSummaryRows(
        string $whereClause,
        array $params,
        ?string $orderBy,
        ?int $limit,
        ?int $offset,
        ZoneSummaryQuery $query
    ): array {
        $limitClause = $limit === null ? '' : 'LIMIT :limit OFFSET :offset';
        $orderClause = $orderBy === null ? '' : 'ORDER BY ' . $orderBy;
        $stmt = $this->pdo->prepare("
            SELECT
                z.id,
                z.name,
                z.city,
                z.type,
                z.status,
                z.hourly_rate_eur AS hourlyRateEur,
                z.latitude,
                z.longitude,
                z.amenities,
                z.opening_hours AS openingHours
            FROM zones z
            {$whereClause}
            {$orderClause}
            {$limitClause}
        ");

        $this->bindQueryParams($stmt, $params);

        if ($limit !== null && $offset !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        }

        $stmt->execute();

        return $this->decodeSummaryRows($stmt->fetchAll(), $query);
    }

    private function countSummaries(string $whereClause, array $params): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM zones z
            {$whereClause}
        ");
        $this->bindQueryParams($stmt, $params);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    private function bindQueryParams(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $name => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue(':' . $name, $value, $type);
        }
    }

    private function decodeSummaryRows(array $rows, ZoneSummaryQuery $query): array
    {
        foreach ($rows as &$item) {
            $item['openingHours'] = $this->decodeOpeningHours($item['openingHours']);

            if ($query->hasCoordinates()) {
                $item['distanceKm'] = round(
                    $this->calculateDistanceKm(
                        (float) $query->latitude,
                        (float) $query->longitude,
                        (float) $item['latitude'],
                        (float) $item['longitude']
                    ),
                    2
                );
            }

            $item['amenities'] = $this->decodeAmenities($item['amenities']);
        }

        unset($item);

        return $rows;
    }

    private function decodeAmenities(string $payload): array
    {
        $amenities = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($amenities) || !array_is_list($amenities)) {
            throw new RuntimeException('Zone amenities must decode to a JSON array.');
        }

        foreach ($amenities as $amenity) {
            if (!is_string($amenity)) {
                throw new RuntimeException('Zone amenities must contain only strings.');
            }
        }

        return $amenities;
    }

    private function decodeOpeningHours(string $payload): array
    {
        $openingHours = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($openingHours) || array_is_list($openingHours)) {
            throw new RuntimeException('Zone opening hours must decode to a JSON object.');
        }

        foreach (['weekdays', 'weekends'] as $key) {
            if (!array_key_exists($key, $openingHours) || !is_string($openingHours[$key])) {
                throw new RuntimeException(sprintf('Zone opening hours must contain a string "%s" field.', $key));
            }
        }

        return $openingHours;
    }

    private function buildSummaryFilters(ZoneSummaryQuery $query): array
    {
        $clauses = [];
        $params = [];

        if ($query->city !== null) {
            $clauses[] = 'z.city = :city';
            $params['city'] = $query->city;
        }

        if ($query->query !== null) {
            $clauses[] = 'LOWER(z.name) LIKE :query';
            $params['query'] = '%' . strtolower($query->query) . '%';
        }

        if ($query->type !== null) {
            $clauses[] = 'z.type = :type';
            $params['type'] = $query->type;
        }

        if ($query->status !== null) {
            $clauses[] = 'z.status = :status';
            $params['status'] = $query->status;
        }

        foreach ($query->amenities ?? [] as $index => $amenity) {
            $key = 'amenity_' . $index;
            $clauses[] = "EXISTS (
                SELECT 1
                FROM json_each(z.amenities)
                WHERE json_each.value = :{$key}
            )";
            $params[$key] = $amenity;
        }

        if ($query->radius !== null && $query->hasCoordinates()) {
            $box = $this->calculateBoundingBox((float) $query->latitude, (float) $query->longitude, $query->radius);
            $clauses[] = 'z.latitude BETWEEN :min_latitude AND :max_latitude';
            $clauses[] = 'z.longitude BETWEEN :min_longitude AND :max_longitude';
            $params['min_latitude'] = $box['minLatitude'];
            $params['max_latitude'] = $box['maxLatitude'];
            $params['min_longitude'] = $box['minLongitude'];
            $params['max_longitude'] = $box['maxLongitude'];
        }

        return [
            $clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses),
            $params,
        ];
    }

    private function resolveStaticOrderBy(string $sort): string
    {
        return match ($sort) {
            'price_asc' => 'z.hourly_rate_eur ASC, z.name ASC, z.id ASC',
            'price_desc' => 'z.hourly_rate_eur DESC, z.name ASC, z.id ASC',
            default => 'z.name ASC, z.id ASC',
        };
    }

    private function isZoneOpenNow(array $zone): bool
    {
        if (($zone['status'] ?? null) !== 'active') {
            return false;
        }

        $openingHours = $zone['openingHours'] ?? null;

        if (!is_array($openingHours)) {
            return false;
        }

        $clock = $this->getZoneClock();
        $ranges = $this->buildRelativeRanges($openingHours, $clock['day']);

        foreach ($ranges as $range) {
            if ($range['start'] <= $clock['minutes'] && $clock['minutes'] < $range['end']) {
                return true;
            }
        }

        return false;
    }

    private function sortSummaries(array &$items, string $sort, ?float $latitude, ?float $longitude): void
    {
        usort($items, function (array $left, array $right) use ($sort, $latitude, $longitude): int {
            return match ($sort) {
                'price_asc' => $this->compareNumbers(
                    (float) $left['hourlyRateEur'],
                    (float) $right['hourlyRateEur'],
                    (string) $left['name'],
                    (string) $right['name']
                ),
                'price_desc' => $this->compareNumbers(
                    (float) $right['hourlyRateEur'],
                    (float) $left['hourlyRateEur'],
                    (string) $left['name'],
                    (string) $right['name']
                ),
                'distance_asc' => $this->compareNumbers(
                    $this->resolveDistanceForSort($left, $latitude, $longitude),
                    $this->resolveDistanceForSort($right, $latitude, $longitude),
                    (string) $left['name'],
                    (string) $right['name']
                ),
                default => strcmp((string) $left['name'], (string) $right['name']),
            };
        });
    }

    private function compareNumbers(float $left, float $right, string $leftName, string $rightName): int
    {
        $comparison = $left <=> $right;

        return $comparison !== 0 ? $comparison : strcmp($leftName, $rightName);
    }

    private function resolveDistanceForSort(array $item, ?float $latitude, ?float $longitude): float
    {
        if ($latitude === null || $longitude === null) {
            return INF;
        }

        return isset($item['distanceKm']) ? (float) $item['distanceKm'] : INF;
    }

    private function calculateDistanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371.0;
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);
        $startLat = deg2rad($lat1);
        $endLat = deg2rad($lat2);
        $a = sin($deltaLat / 2) ** 2
            + cos($startLat) * cos($endLat) * sin($deltaLng / 2) ** 2;

        return $earthRadiusKm * 2 * asin(min(1.0, sqrt($a)));
    }

    private function calculateBoundingBox(float $latitude, float $longitude, float $radiusKm): array
    {
        $earthRadiusKm = 6371.0;
        $latitudeDelta = rad2deg($radiusKm / $earthRadiusKm);
        $longitudeDelta = rad2deg($radiusKm / ($earthRadiusKm * max(cos(deg2rad($latitude)), 0.01)));

        return [
            'minLatitude' => max(-90.0, $latitude - $latitudeDelta),
            'maxLatitude' => min(90.0, $latitude + $latitudeDelta),
            'minLongitude' => max(-180.0, $longitude - $longitudeDelta),
            'maxLongitude' => min(180.0, $longitude + $longitudeDelta),
        ];
    }

    private function getZoneClock(): array
    {
        $now = ($this->currentTime ?? new DateTimeImmutable('now'))
            ->setTimezone(new DateTimeZone(self::ZONE_TIME_ZONE));

        return [
            'day' => (int) $now->format('w'),
            'minutes' => ((int) $now->format('G') * 60) + (int) $now->format('i'),
        ];
    }

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
}
