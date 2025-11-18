<?php
declare(strict_types=1);

namespace ParkingZones\Domain;

final class DistanceCalculator
{
    private const EARTH_RADIUS_KM = 6371.0;

    public function calculateKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);
        $startLat = deg2rad($lat1);
        $endLat = deg2rad($lat2);
        $a = sin($deltaLat / 2) ** 2
            + cos($startLat) * cos($endLat) * sin($deltaLng / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * asin(min(1.0, sqrt($a)));
    }

    /**
     * @return array{minLatitude: float, maxLatitude: float, minLongitude: float, maxLongitude: float}
     */
    public function calculateBoundingBox(float $latitude, float $longitude, float $radiusKm): array
    {
        $latitudeDelta = rad2deg($radiusKm / self::EARTH_RADIUS_KM);
        $longitudeDelta = rad2deg($radiusKm / (self::EARTH_RADIUS_KM * max(cos(deg2rad($latitude)), 0.01)));

        return [
            'minLatitude' => max(-90.0, $latitude - $latitudeDelta),
            'maxLatitude' => min(90.0, $latitude + $latitudeDelta),
            'minLongitude' => max(-180.0, $longitude - $longitudeDelta),
            'maxLongitude' => min(180.0, $longitude + $longitudeDelta),
        ];
    }
}
