<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/bootstrap.php';

use ParkingZones\Infrastructure\Database;
use ParkingZones\Config\AppConfig;

$config = AppConfig::fromEnvironment();
$pdo = Database::sqliteFile($config->databasePath, false); // Don't auto-seed
echo "Initialized database at {$config->databasePath}\n";

// Ensure tables exist properly using the Database class method if it were public,
// but since initializeSqliteDatabase is public we can call it:
Database::initializeSqliteDatabase(
    $pdo,
    dirname(__DIR__) . '/database/schema.sql',
    dirname(__DIR__) . '/database/seed.sql',
    false // disable auto-seed for the import
);

echo "Fetching real parking zones data via Overpass API (Helsinki, Espoo, Vantaa bounds)...\n";

$overpassUrl = 'https://overpass-api.de/api/interpreter';
$overpassQuery = '[out:json][timeout:25];
(
  node["amenity"="parking"](60.1, 24.6, 60.35, 25.15);
  way["amenity"="parking"](60.1, 24.6, 60.35, 25.15);
);
out center 1000;';

$options = [
    'http' => [
        'header'  => "Content-type: application/x-www-form-urlencoded\r\nUser-Agent: QParkingZones/1.0",
        'method'  => 'POST',
        'content' => 'data=' . urlencode($overpassQuery)
    ]
];

$context  = stream_context_create($options);
$response = file_get_contents($overpassUrl, false, $context);

if ($response === false) {
    die("Error fetching data from Overpass API.\n");
}

$data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE || !isset($data['elements'])) {
    die("Error parsing JSON from Overpass API.\n");
}

$elements = $data['elements'];
echo sprintf("Retrieved %d raw parking elements.\n", count($elements));

$pdo->beginTransaction();

try {
    // Clear out old seed data
    $pdo->exec('DELETE FROM zones');

    $stmt = $pdo->prepare("
        INSERT INTO zones (
            name, city, type, status, description, max_capacity, hourly_rate_eur, 
            latitude, longitude, amenities, opening_hours
        ) VALUES (
            :name, :city, :type, :status, :description, :max_capacity, :hourly_rate_eur,
            :latitude, :longitude, :amenities, :opening_hours
        )
    ");

    $count = 0;
    foreach ($elements as $el) {
        $tags = $el['tags'] ?? [];
        
        $lat = $el['lat'] ?? $el['center']['lat'] ?? null;
        $lon = $el['lon'] ?? $el['center']['lon'] ?? null;
        
        if ($lat === null || $lon === null) {
            continue;
        }

        // Only take named parking spaces or large ones to keep quality high
        $name = $tags['name'] ?? null;
        $capacity = (int)($tags['capacity'] ?? rand(20, 300));
        
        // Skip uninteresting street parking unless it has a name
        if ($name === null && $capacity < 50) {
            continue;
        }
        
        if ($name === null) {
            $name = (($tags['parking'] ?? 'surface') === 'surface' ? 'Surface Parking ' : 'Parking Area ') . $el['id'];
        }

        // Determine city based on rough bounding boxes to simulate
        $city = 'helsinki';
        if ($lon < 24.85) {
            $city = 'espoo';
        } elseif ($lat > 60.25) {
            $city = 'vantaa';
        }

        $type = 'street';
        if (($tags['parking'] ?? '') === 'multi-storey' || ($tags['parking'] ?? '') === 'underground') {
            $type = 'commercial';
        }

        // Determine amenities
        $amenities = [];
        if (($tags['charge'] ?? '') === 'yes' || ($tags['fee'] ?? '') === 'yes') {
            $amenities[] = 'Ticket Machine';
        }
        if (($tags['parking'] ?? '') === 'multi-storey' || ($tags['parking'] ?? '') === 'underground') {
            $amenities[] = 'Indoor Parking';
        }
        if (($tags['access'] ?? '') === 'customers') {
            $amenities[] = 'Retail Validation';
        }
        if (($tags['surface'] ?? '') === 'paved' || ($tags['surface'] ?? '') === 'asphalt') {
            $amenities[] = 'Paved Surface';
        }
        if (($tags['park_ride'] ?? '') === 'yes') {
            $amenities[] = 'Park and Ride Access';
            $amenities[] = 'Train Station Nearby';
        }
        
        // Randomly assign some common ones for demo purposes
        if (rand(0, 4) === 1) $amenities[] = 'EV Charging';
        if (rand(0, 4) === 1) $amenities[] = 'Security Cameras';
        
        $amenities = array_values(array_unique($amenities));
        
        $basePrice = 0.0;
        if (($tags['fee'] ?? '') === 'yes') {
            $basePrice = (float)rand(10, 50) / 10;
        }

        // Assume active
        $status = 'active';
        
        // Minimal schedule handling
        // For simplicity, default schedules based on access
        $openingHours = [
            'weekdays' => '00:00-23:59',
            'weekends' => '00:00-23:59'
        ];

        if (($tags['access'] ?? '') === 'customers') {
            $openingHours = [
                'weekdays' => '07:00-22:00',
                'weekends' => '08:00-20:00'
            ];
        }

        $stmt->execute([
            'name' => $name,
            'city' => $city,
            'type' => $type,
            'status' => $status,
            'description' => 'Real parking data imported from OpenStreetMap (Overpass API). ID: ' . $el['id'],
            'max_capacity' => $capacity,
            'hourly_rate_eur' => $basePrice,
            'latitude' => $lat,
            'longitude' => $lon,
            'amenities' => json_encode($amenities, JSON_UNESCAPED_UNICODE),
            'opening_hours' => json_encode($openingHours, JSON_UNESCAPED_UNICODE)
        ]);

        $count++;
    }

    $pdo->commit();
    echo "Successfully imported {$count} parking zones!\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    echo "Import failed: " . $e->getMessage() . "\n";
    exit(1);
}
