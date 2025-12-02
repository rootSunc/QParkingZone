<?php
declare(strict_types=1);

namespace ParkingZones\Repository;

final class ZoneSummaryQuery
{
    public function __construct(
        public readonly ?string $city = null,
        public readonly ?string $query = null,
        public readonly ?string $type = null,
        public readonly ?string $status = null,
        public readonly string $sort = 'name',
        public readonly bool $openNow = false,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?float $radius = null,
        public readonly ?array $amenities = null,
        public readonly int $page = 1,
        public readonly int $limit = 20
    ) {
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
