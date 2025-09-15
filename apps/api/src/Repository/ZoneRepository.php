<?php
declare(strict_types=1);

namespace ParkingZones\Repository;

use PDO;
use RuntimeException;

final class ZoneRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function fetchAllSummaries(?string $city = null): array
    {
        if ($city === null) {
            $sql = "
                SELECT
                    id,
                    name,
                    city,
                    type,
                    status,
                    hourly_rate_eur AS hourlyRateEur,
                    latitude,
                    longitude
                FROM zones
                ORDER BY name ASC
            ";

            return $this->pdo->query($sql)->fetchAll();
        }

        $stmt = $this->pdo->prepare("
            SELECT
                id,
                name,
                city,
                type,
                status,
                hourly_rate_eur AS hourlyRateEur,
                latitude,
                longitude
            FROM zones
            WHERE city = :city
            ORDER BY name ASC
        ");
        $stmt->execute(['city' => $city]);

        return $stmt->fetchAll();
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
}
