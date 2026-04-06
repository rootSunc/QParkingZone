<?php
declare(strict_types=1);

namespace ParkingZones\Repository;

use PDO;
use PDOStatement;
use RuntimeException;

final class ZoneRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function fetchAllSummaries(
        ?string $city = null,
        ?string $query = null,
        ?string $type = null,
        ?string $status = null,
        string $sort = 'name',
        int $page = 1,
        int $limit = 20
    ): array
    {
        [$whereClause, $params] = $this->buildSummaryFilters($city, $query, $type, $status);
        $offset = ($page - 1) * $limit;
        $stmt = $this->pdo->prepare("
            SELECT
                id,
                name,
                city,
                type,
                status,
                hourly_rate_eur AS hourlyRateEur,
                latitude,
                longitude,
                opening_hours AS openingHours
            FROM zones
            {$whereClause}
            ORDER BY {$this->resolveSummarySort($sort)}
            LIMIT :limit OFFSET :offset
        ");

        $this->bindSummaryFilterParams($stmt, $params);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = $stmt->fetchAll();

        foreach ($items as &$item) {
            $item['openingHours'] = $this->decodeOpeningHours($item['openingHours']);
        }

        unset($item);

        return [
            'items' => $items,
            'total' => $this->countSummaries($whereClause, $params),
            'page' => $page,
            'limit' => $limit,
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

        return $zone;
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

    private function buildSummaryFilters(
        ?string $city,
        ?string $query,
        ?string $type,
        ?string $status
    ): array {
        $clauses = [];
        $params = [];

        if ($city !== null) {
            $clauses[] = 'city = :city';
            $params['city'] = $city;
        }

        if ($query !== null) {
            $clauses[] = 'LOWER(name) LIKE :query';
            $params['query'] = '%' . strtolower($query) . '%';
        }

        if ($type !== null) {
            $clauses[] = 'type = :type';
            $params['type'] = $type;
        }

        if ($status !== null) {
            $clauses[] = 'status = :status';
            $params['status'] = $status;
        }

        return [
            $clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses),
            $params,
        ];
    }

    private function countSummaries(string $whereClause, array $params): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM zones
            {$whereClause}
        ");
        $this->bindSummaryFilterParams($stmt, $params);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    private function bindSummaryFilterParams(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $name => $value) {
            $stmt->bindValue(':' . $name, $value, PDO::PARAM_STR);
        }
    }

    private function resolveSummarySort(string $sort): string
    {
        return match ($sort) {
            'price_asc' => 'hourly_rate_eur ASC, name ASC',
            'price_desc' => 'hourly_rate_eur DESC, name ASC',
            default => 'name ASC',
        };
    }
}
