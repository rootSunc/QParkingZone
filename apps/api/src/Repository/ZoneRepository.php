<?php
declare(strict_types=1);

namespace ParkingZones\Repository;

use DateTimeImmutable;
use PDO;
use ParkingZones\Domain\DistanceCalculator;
use ParkingZones\Domain\OpeningHoursEvaluator;
use RuntimeException;

final class ZoneRepository
{
    private readonly DistanceCalculator $distanceCalculator;
    private readonly OpeningHoursEvaluator $openingHoursEvaluator;

    public function __construct(
        private readonly PDO $pdo,
        private readonly ?DateTimeImmutable $currentTime = null
    ) {
        $this->distanceCalculator = new DistanceCalculator();
        $this->openingHoursEvaluator = new OpeningHoursEvaluator($currentTime);
    }

    public function fetchAllSummaries(ZoneSummaryQuery $query): array
    {
        $distanceExpr = $query->hasCoordinates()
            ? sprintf(
                'distance_km(%F, %F, z.latitude, z.longitude)',
                (float) $query->latitude,
                (float) $query->longitude
            )
            : null;
        [$whereClause, $params] = $this->buildSummaryFilters($query, $distanceExpr);
        $offset = ($query->page - 1) * $query->limit;
        $orderBy = $this->resolveOrderBy($query, $distanceExpr);
        $distanceSelect = $distanceExpr === null ? '' : ", {$distanceExpr} AS distanceKm";

        return [
            'items' => $this->fetchSummaryRows(
                $whereClause,
                $params,
                $orderBy,
                $query->limit,
                $offset,
                $query,
                $distanceSelect
            ),
            'total' => $this->countSummaries($whereClause, $params),
            'page' => $query->page,
            'limit' => $query->limit,
        ];
    }

    public function ping(): bool
    {
        $this->pdo->query('SELECT 1')->fetchColumn();

        return true;
    }

    public function countZones(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM zones')->fetchColumn();
    }

    public function fetchFacets(?string $city): array
    {
        [$whereClause, $params] = $this->buildFacetFilters($city);

        return [
            'city' => $city,
            'types' => $this->fetchGroupedCounts('z.type', $whereClause, $params),
            'statuses' => $this->fetchGroupedCounts('z.status', $whereClause, $params),
            'amenities' => $this->fetchAmenityCounts($whereClause, $params),
        ];
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
        $availability = $this->openingHoursEvaluator->evaluate(
            (string) $zone['status'],
            $zone['openingHours']
        );
        $zone['isOpen'] = $availability['isOpen'];
        $zone['availability'] = [
            'state' => $availability['state'],
            'badge' => $availability['badge'],
            'detail' => $availability['detail'],
            'schedule' => $availability['schedule'],
        ];

        return $zone;
    }

    private function fetchSummaryRows(
        string $whereClause,
        array $params,
        string $orderBy,
        int $limit,
        int $offset,
        ZoneSummaryQuery $query,
        string $distanceSelect
    ): array {
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
                {$distanceSelect}
            FROM zones z
            {$whereClause}
            ORDER BY {$orderBy}
            LIMIT :limit OFFSET :offset
        ");

        $this->bindQueryParams($stmt, $params);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
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

    private function fetchGroupedCounts(string $expression, string $whereClause, array $params): array
    {
        $stmt = $this->pdo->prepare("
            SELECT {$expression} AS value, COUNT(*) AS count
            FROM zones z
            {$whereClause}
            GROUP BY {$expression}
            ORDER BY value ASC
        ");
        $this->bindQueryParams($stmt, $params);
        $stmt->execute();

        return array_map(
            fn (array $row): array => [
                'value' => (string) $row['value'],
                'count' => (int) $row['count'],
            ],
            $stmt->fetchAll()
        );
    }

    private function fetchAmenityCounts(string $whereClause, array $params): array
    {
        // Amenities stay JSON-encoded; junction table deferred until import volume
        // makes json_each facet/filter cost dominate (typically >>1k zones).
        $stmt = $this->pdo->prepare("
            SELECT json_each.value AS value, COUNT(*) AS count
            FROM zones z, json_each(z.amenities)
            {$whereClause}
            GROUP BY json_each.value
            ORDER BY value ASC
        ");
        $this->bindQueryParams($stmt, $params);
        $stmt->execute();

        return array_map(
            fn (array $row): array => [
                'value' => (string) $row['value'],
                'count' => (int) $row['count'],
            ],
            $stmt->fetchAll()
        );
    }

    private function bindQueryParams(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $name => $value) {
            if (is_int($value)) {
                $stmt->bindValue(':' . $name, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':' . $name, $value, PDO::PARAM_STR);
            }
        }
    }

    private function decodeSummaryRows(array $rows, ZoneSummaryQuery $query): array
    {
        foreach ($rows as &$item) {
            $openingHours = $this->decodeOpeningHours($item['openingHours']);
            $item['amenities'] = $this->decodeAmenities($item['amenities']);

            if (array_key_exists('distanceKm', $item) && $item['distanceKm'] !== null) {
                $item['distanceKm'] = round((float) $item['distanceKm'], 2);
            } elseif ($query->hasCoordinates()) {
                $item['distanceKm'] = round(
                    $this->distanceCalculator->calculateKm(
                        (float) $query->latitude,
                        (float) $query->longitude,
                        (float) $item['latitude'],
                        (float) $item['longitude']
                    ),
                    2
                );
            }

            $availability = $this->openingHoursEvaluator->evaluate(
                (string) $item['status'],
                $openingHours
            );
            $item['isOpen'] = $availability['isOpen'];
            $item['availability'] = [
                'state' => $availability['state'],
                'badge' => $availability['badge'],
                'detail' => $availability['detail'],
                'schedule' => $availability['schedule'],
            ];

            // Slim list payload: schedule lives on availability; omit full openingHours.
            unset($item['openingHours']);
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

    private function buildSummaryFilters(ZoneSummaryQuery $query, ?string $distanceExpr): array
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

        if ($query->radius !== null && $distanceExpr !== null) {
            $box = $this->distanceCalculator->calculateBoundingBox(
                (float) $query->latitude,
                (float) $query->longitude,
                $query->radius
            );
            $clauses[] = 'z.latitude BETWEEN :min_latitude AND :max_latitude';
            $clauses[] = 'z.longitude BETWEEN :min_longitude AND :max_longitude';
            $clauses[] = "{$distanceExpr} <= :radius";
            $params['min_latitude'] = $box['minLatitude'];
            $params['max_latitude'] = $box['maxLatitude'];
            $params['min_longitude'] = $box['minLongitude'];
            $params['max_longitude'] = $box['maxLongitude'];
            $params['radius'] = $query->radius;
        }

        if ($query->openNow) {
            $clauses[] = 'zone_is_open_now(z.status, z.opening_hours) = 1';
        }

        return [
            $clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses),
            $params,
        ];
    }

    private function buildFacetFilters(?string $city): array
    {
        if ($city === null) {
            return ['', []];
        }

        return [
            'WHERE z.city = :city',
            ['city' => $city],
        ];
    }

    private function resolveOrderBy(ZoneSummaryQuery $query, ?string $distanceExpr): string
    {
        return match ($query->sort) {
            'price_asc' => 'z.hourly_rate_eur ASC, z.name ASC, z.id ASC',
            'price_desc' => 'z.hourly_rate_eur DESC, z.name ASC, z.id ASC',
            'distance_asc' => $distanceExpr !== null
                ? "{$distanceExpr} ASC, z.name ASC, z.id ASC"
                : 'z.name ASC, z.id ASC',
            default => 'z.name ASC, z.id ASC',
        };
    }
}
