<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/bootstrap.php';

use ParkingZones\Config\AppConfig;
use ParkingZones\Import\OverpassClient;
use ParkingZones\Import\ParkingElementNormalizer;
use ParkingZones\Import\ZoneUpsertImporter;
use ParkingZones\Infrastructure\Database;

$config = AppConfig::fromEnvironment();
$pdo = Database::sqliteFile($config->databasePath, $config->autoSeed);

echo "Initialized database at {$config->databasePath}\n";
echo "Fetching parking zones from OpenStreetMap via Overpass API...\n";

$client = new OverpassClient();
$normalizer = new ParkingElementNormalizer();

try {
    $elements = $client->fetchParkingElements();
} catch (Throwable $exception) {
    fwrite(STDERR, "Import failed before database changes: {$exception->getMessage()}\n");
    exit(1);
}

echo sprintf("Retrieved %d raw parking elements.\n", count($elements));

$importedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$zones = [];

foreach ($elements as $element) {
    $zone = $normalizer->normalize($element, $importedAt);

    if ($zone !== null) {
        $zones[] = $zone;
    }
}

if ($zones === []) {
    fwrite(STDERR, "No usable parking zones found; keeping existing database untouched.\n");
    exit(1);
}

try {
    $count = (new ZoneUpsertImporter($pdo))->import($zones);
    echo sprintf("Imported or updated %d deterministic parking zones.\n", $count);
} catch (Throwable $exception) {
    fwrite(STDERR, "Import failed: {$exception->getMessage()}\n");
    exit(1);
}
