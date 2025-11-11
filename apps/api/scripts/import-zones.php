<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/bootstrap.php';

use ParkingZones\Config\AppConfig;
use ParkingZones\Infrastructure\Database;

const SOURCE_PROVIDER = 'openstreetmap';
const DEFAULT_OVERPASS_URLS = [
    'https://overpass-api.de/api/interpreter',
    'https://overpass.private.coffee/api/interpreter',
];
const CITY_BOUNDS = [
    'helsinki' => ['minLat' => 60.10, 'minLon' => 24.80, 'maxLat' => 60.32, 'maxLon' => 25.25],
    'espoo' => ['minLat' => 60.10, 'minLon' => 24.45, 'maxLat' => 60.35, 'maxLon' => 24.90],
    'vantaa' => ['minLat' => 60.22, 'minLon' => 24.75, 'maxLat' => 60.38, 'maxLon' => 25.25],
];

$config = AppConfig::fromEnvironment();
$pdo = Database::sqliteFile($config->databasePath, $config->autoSeed);

echo "Initialized database at {$config->databasePath}\n";
echo "Fetching parking zones from OpenStreetMap via Overpass API...\n";

try {
    $elements = fetchParkingElements();
} catch (Throwable $exception) {
    fwrite(STDERR, "Import failed before database changes: {$exception->getMessage()}\n");
    exit(1);
}

echo sprintf("Retrieved %d raw parking elements.\n", count($elements));

$importedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$zones = [];

foreach ($elements as $element) {
    $zone = normalizeParkingElement($element, $importedAt);

    if ($zone !== null) {
        $zones[] = $zone;
    }
}

if ($zones === []) {
    fwrite(STDERR, "No usable parking zones found; keeping existing database untouched.\n");
    exit(1);
}

$pdo->beginTransaction();

try {
    $pdo->exec('CREATE TEMP TABLE current_import_sources (source_external_id TEXT PRIMARY KEY)');
    $seenStmt = $pdo->prepare('INSERT INTO current_import_sources (source_external_id) VALUES (:source_external_id)');
    $upsertStmt = $pdo->prepare("
        INSERT INTO zones (
            name,
            city,
            type,
            status,
            description,
            max_capacity,
            hourly_rate_eur,
            latitude,
            longitude,
            amenities,
            opening_hours,
            source_provider,
            source_external_id,
            source_updated_at,
            source_payload
        ) VALUES (
            :name,
            :city,
            :type,
            :status,
            :description,
            :max_capacity,
            :hourly_rate_eur,
            :latitude,
            :longitude,
            :amenities,
            :opening_hours,
            :source_provider,
            :source_external_id,
            :source_updated_at,
            :source_payload
        )
        ON CONFLICT(source_provider, source_external_id) WHERE source_external_id IS NOT NULL
        DO UPDATE SET
            name = excluded.name,
            city = excluded.city,
            type = excluded.type,
            status = excluded.status,
            description = excluded.description,
            max_capacity = excluded.max_capacity,
            hourly_rate_eur = excluded.hourly_rate_eur,
            latitude = excluded.latitude,
            longitude = excluded.longitude,
            amenities = excluded.amenities,
            opening_hours = excluded.opening_hours,
            source_updated_at = excluded.source_updated_at,
            source_payload = excluded.source_payload
    ");

    $pdo->exec("DELETE FROM zones WHERE source_provider = 'seed'");

    foreach ($zones as $zone) {
        $seenStmt->execute(['source_external_id' => $zone['source_external_id']]);
        $upsertStmt->execute($zone);
    }

    $pdo->exec("
        DELETE FROM zones
        WHERE source_provider = 'openstreetmap'
          AND source_external_id NOT IN (
            SELECT source_external_id
            FROM current_import_sources
          )
    ");
    $pdo->exec('DROP TABLE current_import_sources');
    $pdo->commit();

    echo sprintf("Imported or updated %d deterministic parking zones.\n", count($zones));
} catch (Throwable $exception) {
    $pdo->rollBack();
    fwrite(STDERR, "Import failed: {$exception->getMessage()}\n");
    exit(1);
}

function fetchParkingElements(): array
{
    $overpassQuery = '[out:json][timeout:30];
(
  node["amenity"="parking"](60.10,24.45,60.38,25.25);
  way["amenity"="parking"](60.10,24.45,60.38,25.25);
);
out center 1500;';

    $errors = [];

    foreach (overpassUrls() as $url) {
        echo "Trying Overpass endpoint {$url}...\n";

        try {
            return fetchParkingElementsFromUrl($url, $overpassQuery);
        } catch (Throwable $exception) {
            $errors[] = "{$url}: {$exception->getMessage()}";
            fwrite(STDERR, "Overpass endpoint failed: {$exception->getMessage()}\n");
        }
    }

    throw new RuntimeException(
        'Error fetching data from Overpass API endpoints. ' . implode(' ', $errors)
    );
}

function overpassUrls(): array
{
    $configured = getenv('PARKING_ZONES_OVERPASS_URLS');
    if ($configured === false || trim($configured) === '') {
        return DEFAULT_OVERPASS_URLS;
    }

    $urls = [];
    foreach (explode(',', $configured) as $url) {
        $url = trim($url);

        if ($url !== '' && !in_array($url, $urls, true)) {
            $urls[] = $url;
        }
    }

    return $urls !== [] ? $urls : DEFAULT_OVERPASS_URLS;
}

function fetchParkingElementsFromUrl(string $url, string $overpassQuery): array
{
    $context = stream_context_create([
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\nUser-Agent: QParkingZones/1.0\r\n",
            'method' => 'POST',
            'content' => 'data=' . urlencode($overpassQuery),
            'timeout' => 35,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        $statusLine = httpStatusLine($http_response_header ?? []);
        $message = 'Request failed';

        if ($statusLine !== null) {
            $message .= " ({$statusLine})";
        }

        throw new RuntimeException($message . '.');
    }

    try {
        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new RuntimeException('Overpass API returned invalid JSON.', 0, $exception);
    }

    if (!is_array($data) || !isset($data['elements']) || !is_array($data['elements'])) {
        throw new RuntimeException('Overpass API returned an unexpected payload.');
    }

    return $data['elements'];
}

function httpStatusLine(array $headers): ?string
{
    foreach ($headers as $header) {
        if (is_string($header) && str_starts_with($header, 'HTTP/')) {
            return $header;
        }
    }

    return null;
}

function normalizeParkingElement(array $element, DateTimeImmutable $importedAt): ?array
{
    $tags = is_array($element['tags'] ?? null) ? $element['tags'] : [];
    $latitude = $element['lat'] ?? $element['center']['lat'] ?? null;
    $longitude = $element['lon'] ?? $element['center']['lon'] ?? null;

    if (!is_numeric($latitude) || !is_numeric($longitude)) {
        return null;
    }

    $latitude = (float) $latitude;
    $longitude = (float) $longitude;
    $city = resolveCity($latitude, $longitude);

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
    $capacity = resolveCapacity($tags, $parkingType);
    $rate = resolveHourlyRate($tags, $type);
    $amenities = resolveAmenities($tags, $parkingType);
    $openingHours = resolveOpeningHours($tags);
    $name = resolveName($tags, $parkingType, $osmId);
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
        'source_provider' => SOURCE_PROVIDER,
        'source_external_id' => $sourceExternalId,
        'source_updated_at' => $importedAt->format(DATE_ATOM),
        'source_payload' => json_encode([
            'osmType' => $osmType,
            'osmId' => $osmId,
            'tags' => $tags,
            'derived' => [
                'parkingType' => $parkingType,
                'hourlyRateEur' => $rate,
                'maxCapacity' => $capacity,
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ];
}

function resolveCity(float $latitude, float $longitude): ?string
{
    foreach (CITY_BOUNDS as $city => $bounds) {
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

function resolveCapacity(array $tags, string $parkingType): int
{
    $capacity = parsePositiveInteger($tags['capacity'] ?? null);

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

function resolveHourlyRate(array $tags, string $type): float
{
    $charge = parseHourlyCharge($tags['charge'] ?? null);

    if ($charge !== null) {
        return $charge;
    }

    if (strtolower((string) ($tags['fee'] ?? '')) !== 'yes') {
        return 0.0;
    }

    return $type === 'commercial' ? 3.0 : 2.5;
}

function resolveAmenities(array $tags, string $parkingType): array
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
        parsePositiveInteger($tags['capacity:charging'] ?? null) !== null
        || strtolower((string) ($tags['charging_station'] ?? '')) === 'yes'
    ) {
        $amenities[] = 'EV Charging';
    }

    if (
        parsePositiveInteger($tags['capacity:disabled'] ?? null) !== null
        || strtolower((string) ($tags['disabled'] ?? '')) === 'yes'
    ) {
        $amenities[] = 'Barrier-Free Access';
    }

    if (strtolower((string) ($tags['surveillance'] ?? '')) === 'yes') {
        $amenities[] = 'Security Cameras';
    }

    return array_values(array_unique($amenities));
}

function resolveOpeningHours(array $tags): array
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

function resolveName(array $tags, string $parkingType, string $osmId): string
{
    $name = trim((string) ($tags['name'] ?? ''));

    if ($name !== '') {
        return $name;
    }

    $label = $parkingType === 'surface' ? 'Surface Parking' : 'Parking Area';

    return "{$label} {$osmId}";
}

function parsePositiveInteger(mixed $value): ?int
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

function parseHourlyCharge(mixed $value): ?float
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
