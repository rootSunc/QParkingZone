<?php
declare(strict_types=1);

namespace ParkingZones\Http;

use ParkingZones\Repository\ZoneSummaryQuery;

final class ZoneSummaryQueryParser
{
    private const MAX_PAGE_SIZE = 100;
    private const ALLOWED_CITIES = ['helsinki', 'espoo', 'vantaa'];
    private const ALLOWED_SORTS = ['name', 'price_asc', 'price_desc', 'distance_asc'];
    private const ALLOWED_STATUSES = ['active', 'inactive'];

    public function parse(array $queryParams): ZoneSummaryQuery
    {
        $city = $this->readNormalizedString($queryParams, 'city');
        if ($city !== null && !in_array($city, self::ALLOWED_CITIES, true)) {
            throw new InvalidQueryParameter('Unsupported city. Use helsinki, espoo, or vantaa.');
        }

        $status = $this->readNormalizedString($queryParams, 'status');
        if ($status !== null && !in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new InvalidQueryParameter('Unsupported status. Use active or inactive.');
        }

        $sort = $this->readSort($queryParams);
        $latitude = $this->readCoordinate($queryParams, 'lat', -90.0, 90.0);
        $longitude = $this->readCoordinate($queryParams, 'lng', -180.0, 180.0);

        if (($latitude === null) !== ($longitude === null)) {
            throw new InvalidQueryParameter('lat and lng must be provided together.');
        }

        $radius = $this->readFloat($queryParams, 'radius', 0.1, 500.0);
        if ($radius !== null && ($latitude === null || $longitude === null)) {
            throw new InvalidQueryParameter('radius requires both lat and lng.');
        }

        if ($sort === 'distance_asc' && ($latitude === null || $longitude === null)) {
            throw new InvalidQueryParameter('distance_asc sort requires both lat and lng.');
        }

        return new ZoneSummaryQuery(
            city: $city,
            query: $this->readNormalizedString($queryParams, 'q'),
            type: $this->readNormalizedString($queryParams, 'type'),
            status: $status,
            sort: $sort,
            openNow: $this->readBoolean($queryParams, 'open_now', false),
            latitude: $latitude,
            longitude: $longitude,
            radius: $radius,
            amenities: $this->readArrayString($queryParams, 'amenities'),
            page: $this->readPositiveInteger($queryParams, 'page', 1),
            limit: $this->readPositiveInteger($queryParams, 'limit', 20, self::MAX_PAGE_SIZE)
        );
    }

    private function readNormalizedString(array $queryParams, string $name): ?string
    {
        $value = $this->readScalar($queryParams, $name);

        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim($value));

        return $normalized === '' ? null : $normalized;
    }

    private function readSort(array $queryParams): string
    {
        $value = $this->readScalar($queryParams, 'sort');

        if ($value === null || trim($value) === '') {
            return 'name';
        }

        $sort = trim($value);
        if (!in_array($sort, self::ALLOWED_SORTS, true)) {
            throw new InvalidQueryParameter('Unsupported sort. Use name, price_asc, price_desc, or distance_asc.');
        }

        return $sort;
    }

    private function readBoolean(array $queryParams, string $name, bool $default): bool
    {
        $value = $this->readScalar($queryParams, $name);

        if ($value === null) {
            return $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($parsed === null) {
            throw new InvalidQueryParameter(sprintf('Query parameter "%s" must be a boolean.', $name));
        }

        return $parsed;
    }

    private function readCoordinate(array $queryParams, string $name, float $minimum, float $maximum): ?float
    {
        return $this->readFloat($queryParams, $name, $minimum, $maximum);
    }

    private function readFloat(array $queryParams, string $name, float $minimum, float $maximum): ?float
    {
        $value = $this->readScalar($queryParams, $name);

        if ($value === null || trim($value) === '') {
            return null;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
        if ($parsed === null || $parsed < $minimum || $parsed > $maximum) {
            throw new InvalidQueryParameter(sprintf(
                'Query parameter "%s" must be between %s and %s.',
                $name,
                $minimum,
                $maximum
            ));
        }

        return (float) $parsed;
    }

    private function readPositiveInteger(
        array $queryParams,
        string $name,
        int $default,
        ?int $maximum = null
    ): int {
        $value = $this->readScalar($queryParams, $name);

        if ($value === null || trim($value) === '') {
            return $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        if ($parsed === null || $parsed < 1) {
            throw new InvalidQueryParameter(sprintf('Query parameter "%s" must be a positive integer.', $name));
        }

        if ($maximum !== null && $parsed > $maximum) {
            throw new InvalidQueryParameter(sprintf('Query parameter "%s" must be no greater than %d.', $name, $maximum));
        }

        return (int) $parsed;
    }

    private function readArrayString(array $queryParams, string $name): ?array
    {
        $value = $this->readScalar($queryParams, $name);

        if ($value === null) {
            return null;
        }

        $parts = array_values(array_unique(array_filter(
            array_map('trim', explode(',', $value)),
            fn (string $part): bool => $part !== ''
        )));

        return $parts === [] ? null : $parts;
    }

    private function readScalar(array $queryParams, string $name): ?string
    {
        if (!array_key_exists($name, $queryParams)) {
            return null;
        }

        $value = $queryParams[$name];
        if (is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        throw new InvalidQueryParameter(sprintf('Query parameter "%s" must be a scalar value.', $name));
    }
}
