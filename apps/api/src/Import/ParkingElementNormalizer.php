<?php
declare(strict_types=1);

namespace ParkingZones\Import;

use DateTimeImmutable;

final class ParkingElementNormalizer
{
    public const SOURCE_PROVIDER = 'openstreetmap';

    private const CITY_BOUNDS = [
        'helsinki' => ['minLat' => 60.10, 'minLon' => 24.80, 'maxLat' => 60.32, 'maxLon' => 25.25],
        'espoo' => ['minLat' => 60.10, 'minLon' => 24.45, 'maxLat' => 60.35, 'maxLon' => 24.90],
        'vantaa' => ['minLat' => 60.22, 'minLon' => 24.75, 'maxLat' => 60.38, 'maxLon' => 25.25],
    ];

    public function normalize(array $element, DateTimeImmutable $importedAt): ?array
    {
        $tags = is_array($element['tags'] ?? null) ? $element['tags'] : [];
        $latitude = $element['lat'] ?? $element['center']['lat'] ?? null;
        $longitude = $element['lon'] ?? $element['center']['lon'] ?? null;

        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return null;
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;
        $city = $this->resolveCity($latitude, $longitude);

        if ($city === null) {
            return null;
        }

        $osmType = is_string($element['type'] ?? null) ? $element['type'] : 'element';
        $osmId = (string) ($element['id'] ?? '');
        if ($osmId === '') {
            return null;
        }

        $parkingType = is_string($tags['parking'] ?? null) ? strtolower($tags['parking']) : 'surface';
        $type = in_array($parkingType, ['multi-storey', 'underground'], true) ? 'commercial' : 'street';
        $capacity = $this->resolveCapacity($tags, $parkingType);
        $rate = $this->resolveHourlyRate($tags, $type);
        $amenities = $this->resolveAmenities($tags, $parkingType);
        $openingHours = $this->resolveOpeningHours($tags);
        $name = $this->resolveName($tags, $parkingType, $osmId);
        $sourceExternalId = "{$osmType}:{$osmId}";

        return [
            'name' => $name,
            'city' => $city,
            'type' => $type,
            'status' => 'active',
            'description' => sprintf(
                'Parking data imported from OpenStreetMap. Capacity and price fields are derived from explicit OSM tags when available, otherwise deterministic type-based defaults are used. Source: %s.',
                $sourceExternalId
            ),
            'max_capacity' => $capacity,
            'hourly_rate_eur' => $rate,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'amenities' => json_encode($amenities, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'opening_hours' => json_encode($openingHours, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'source_provider' => self::SOURCE_PROVIDER,
            'source_external_id' => $sourceExternalId,
            'source_updated_at' => $importedAt->format(DATE_ATOM),
            // Keep a compact provenance record instead of the full OSM tag dump.
            'source_payload' => json_encode([
                'osmType' => $osmType,
                'osmId' => $osmId,
                'derived' => [
                    'parkingType' => $parkingType,
                    'hourlyRateEur' => $rate,
                    'maxCapacity' => $capacity,
                    'tagKeys' => array_keys($tags),
                ],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    private function resolveCity(float $latitude, float $longitude): ?string
    {
        foreach (self::CITY_BOUNDS as $city => $bounds) {
            if (
                $latitude >= $bounds['minLat']
                && $latitude <= $bounds['maxLat']
                && $longitude >= $bounds['minLon']
                && $longitude <= $bounds['maxLon']
            ) {
                return $city;
            }
        }

        return null;
    }

    private function resolveCapacity(array $tags, string $parkingType): int
    {
        $capacity = $this->parsePositiveInteger($tags['capacity'] ?? null);

        if ($capacity !== null) {
            return min($capacity, 5000);
        }

        return match ($parkingType) {
            'multi-storey' => 250,
            'underground' => 180,
            'street' => 20,
            default => 60,
        };
    }

    private function resolveHourlyRate(array $tags, string $type): float
    {
        $charge = $this->parseHourlyCharge($tags['charge'] ?? null);

        if ($charge !== null) {
            return $charge;
        }

        if (strtolower((string) ($tags['fee'] ?? '')) !== 'yes') {
            return 0.0;
        }

        return $type === 'commercial' ? 3.0 : 2.5;
    }

    private function resolveAmenities(array $tags, string $parkingType): array
    {
        $amenities = [];

        if (strtolower((string) ($tags['fee'] ?? '')) === 'yes') {
            $amenities[] = 'Ticket Machine';
        }

        if (in_array($parkingType, ['multi-storey', 'underground'], true)) {
            $amenities[] = 'Indoor Parking';
        }

        if (strtolower((string) ($tags['access'] ?? '')) === 'customers') {
            $amenities[] = 'Retail Validation';
        }

        if (in_array(strtolower((string) ($tags['surface'] ?? '')), ['paved', 'asphalt', 'concrete'], true)) {
            $amenities[] = 'Paved Surface';
        }

        if (strtolower((string) ($tags['park_ride'] ?? '')) === 'yes') {
            $amenities[] = 'Park and Ride Access';
            $amenities[] = 'Train Station Nearby';
        }

        if (
            $this->parsePositiveInteger($tags['capacity:charging'] ?? null) !== null
            || strtolower((string) ($tags['charging_station'] ?? '')) === 'yes'
        ) {
            $amenities[] = 'EV Charging';
        }

        if (
            $this->parsePositiveInteger($tags['capacity:disabled'] ?? null) !== null
            || strtolower((string) ($tags['disabled'] ?? '')) === 'yes'
        ) {
            $amenities[] = 'Barrier-Free Access';
        }

        if (strtolower((string) ($tags['surveillance'] ?? '')) === 'yes') {
            $amenities[] = 'Security Cameras';
        }

        return array_values(array_unique($amenities));
    }

    private function resolveOpeningHours(array $tags): array
    {
        $openingHours = strtolower(trim((string) ($tags['opening_hours'] ?? '')));

        if ($openingHours === '24/7') {
            return [
                'weekdays' => '00:00-23:59',
                'weekends' => '00:00-23:59',
            ];
        }

        if (strtolower((string) ($tags['access'] ?? '')) === 'customers') {
            return [
                'weekdays' => '07:00-22:00',
                'weekends' => '08:00-20:00',
            ];
        }

        return [
            'weekdays' => '00:00-23:59',
            'weekends' => '00:00-23:59',
        ];
    }

    private function resolveName(array $tags, string $parkingType, string $osmId): string
    {
        $name = trim((string) ($tags['name'] ?? ''));

        if ($name !== '') {
            return $name;
        }

        $label = $parkingType === 'surface' ? 'Surface Parking' : 'Parking Area';

        return "{$label} {$osmId}";
    }

    private function parsePositiveInteger(mixed $value): ?int
    {
        if (!is_scalar($value)) {
            return null;
        }

        if (!preg_match('/\d+/', (string) $value, $match)) {
            return null;
        }

        $parsed = (int) $match[0];

        return $parsed > 0 ? $parsed : null;
    }

    private function parseHourlyCharge(mixed $value): ?float
    {
        if (!is_scalar($value)) {
            return null;
        }

        $normalized = str_replace(',', '.', strtolower((string) $value));

        if (!preg_match('/(\d+(?:\.\d+)?)/', $normalized, $match)) {
            return null;
        }

        $amount = (float) $match[1];
        if ($amount <= 0) {
            return null;
        }

        if (str_contains($normalized, '/day') || str_contains($normalized, 'per day')) {
            return round($amount / 8, 2);
        }

        return round($amount, 2);
    }
}
