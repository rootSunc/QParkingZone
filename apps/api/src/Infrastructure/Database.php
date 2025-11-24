<?php
declare(strict_types=1);

namespace ParkingZones\Infrastructure;

use DateTimeImmutable;
use PDO;
use ParkingZones\Domain\DistanceCalculator;
use ParkingZones\Domain\OpeningHoursEvaluator;
use RuntimeException;
use Throwable;

final class Database
{
    public static function connect(string $dsn): PDO
    {
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }

    /**
     * Open an existing SQLite database for request handling (no schema migration).
     */
    public static function connectSqliteFile(
        string $path,
        ?DateTimeImmutable $currentTime = null
    ): PDO {
        if (!is_file($path)) {
            throw new RuntimeException(sprintf(
                'Database file "%s" does not exist. Run scripts/init-db.php first.',
                $path
            ));
        }

        $pdo = self::connect('sqlite:' . $path);
        self::configureSqliteConnection($pdo, $currentTime);

        return $pdo;
    }

    /**
     * Create/migrate schema and optionally seed, then return a tuned connection.
     */
    public static function sqliteFile(
        string $path,
        bool $autoSeed = true,
        ?DateTimeImmutable $currentTime = null
    ): PDO {
        self::ensureDirectoryExists(dirname($path));

        $pdo = self::connect('sqlite:' . $path);
        self::configureSqliteConnection($pdo, $currentTime);

        self::initializeSqliteDatabase(
            $pdo,
            dirname(__DIR__, 2) . '/database/schema.sql',
            dirname(__DIR__, 2) . '/database/seed.sql',
            $autoSeed
        );

        return $pdo;
    }

    public static function configureSqliteConnection(
        PDO $pdo,
        ?DateTimeImmutable $currentTime = null
    ): void {
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA busy_timeout=5000');
        self::registerSqliteFunctions($pdo, $currentTime);
    }

    public static function registerSqliteFunctions(
        PDO $pdo,
        ?DateTimeImmutable $currentTime = null
    ): void {
        $distance = new DistanceCalculator();
        $hours = new OpeningHoursEvaluator($currentTime);

        $pdo->sqliteCreateFunction(
            'distance_km',
            static fn (
                mixed $lat1,
                mixed $lng1,
                mixed $lat2,
                mixed $lng2
            ): float => $distance->calculateKm(
                (float) $lat1,
                (float) $lng1,
                (float) $lat2,
                (float) $lng2
            ),
            4
        );

        $pdo->sqliteCreateFunction(
            'zone_is_open_now',
            static function (mixed $status, mixed $openingHoursJson) use ($hours): int {
                if (!is_string($status) || !is_string($openingHoursJson)) {
                    return 0;
                }

                try {
                    $openingHours = json_decode($openingHoursJson, true, 512, JSON_THROW_ON_ERROR);
                } catch (Throwable) {
                    return 0;
                }

                if (!is_array($openingHours) || array_is_list($openingHours)) {
                    return 0;
                }

                return $hours->isOpenNow($status, $openingHours) ? 1 : 0;
            },
            2
        );
    }

    public static function initializeSqliteDatabase(
        PDO $pdo,
        string $schemaPath,
        string $seedPath,
        bool $autoSeed = true
    ): void {
        $schemaSql = self::readSqlFile($schemaPath);

        if (!self::zonesTableExists($pdo)) {
            $pdo->exec($schemaSql);
        } elseif (!self::zonesTableHasExpectedSchema($pdo)) {
            self::migrateZonesTable($pdo, $schemaSql);
        } else {
            $pdo->exec($schemaSql);
        }

        self::ensureZonesIndexes($pdo);

        if ($autoSeed && self::zonesTableIsEmpty($pdo)) {
            $pdo->exec(self::readSqlFile($seedPath));
        }
    }

    private static function ensureDirectoryExists(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Failed to create directory "%s".', $directory));
        }
    }

    private static function zonesTableExists(PDO $pdo): bool
    {
        $stmt = $pdo->prepare("
            SELECT name
            FROM sqlite_master
            WHERE type = 'table' AND name = :table
            LIMIT 1
        ");
        $stmt->execute(['table' => 'zones']);

        return $stmt->fetchColumn() !== false;
    }

    private static function zonesTableIsEmpty(PDO $pdo): bool
    {
        return (int) $pdo->query('SELECT COUNT(*) FROM zones')->fetchColumn() === 0;
    }

    private static function zonesTableHasExpectedSchema(PDO $pdo): bool
    {
        $stmt = $pdo->prepare("
            SELECT sql
            FROM sqlite_master
            WHERE type = 'table' AND name = :table
            LIMIT 1
        ");
        $stmt->execute(['table' => 'zones']);
        $sql = $stmt->fetchColumn();

        if (!is_string($sql) || $sql === '') {
            return false;
        }

        $normalized = strtolower($sql);

        return str_contains($normalized, 'json_valid(amenities)')
            && str_contains($normalized, "json_type(amenities) = 'array'")
            && str_contains($normalized, 'json_valid(opening_hours)')
            && str_contains($normalized, "json_type(opening_hours) = 'object'")
            && str_contains($normalized, 'city text not null')
            && str_contains($normalized, 'source_provider text not null')
            && str_contains($normalized, 'json_valid(source_payload)');
    }

    private static function migrateZonesTable(PDO $pdo, string $schemaSql): void
    {
        $pdo->beginTransaction();

        try {
            $pdo->exec('ALTER TABLE zones RENAME TO zones_legacy');
            $pdo->exec($schemaSql);
            $citySelect = self::tableHasColumn($pdo, 'zones_legacy', 'city')
                ? 'city'
                : "'helsinki' AS city";
            $sourceProviderSelect = self::tableHasColumn($pdo, 'zones_legacy', 'source_provider')
                ? 'source_provider'
                : "'seed' AS source_provider";
            $sourceExternalIdSelect = self::tableHasColumn($pdo, 'zones_legacy', 'source_external_id')
                ? 'source_external_id'
                : 'NULL AS source_external_id';
            $sourceUpdatedAtSelect = self::tableHasColumn($pdo, 'zones_legacy', 'source_updated_at')
                ? 'source_updated_at'
                : 'NULL AS source_updated_at';
            $sourcePayloadSelect = self::tableHasColumn($pdo, 'zones_legacy', 'source_payload')
                ? 'source_payload'
                : "'{}' AS source_payload";
            $pdo->exec("
                INSERT INTO zones (
                    id,
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
                )
                SELECT
                    id,
                    name,
                    {$citySelect},
                    type,
                    status,
                    description,
                    max_capacity,
                    hourly_rate_eur,
                    latitude,
                    longitude,
                    amenities,
                    opening_hours,
                    {$sourceProviderSelect},
                    {$sourceExternalIdSelect},
                    {$sourceUpdatedAtSelect},
                    {$sourcePayloadSelect}
                FROM zones_legacy
            ");
            $pdo->exec('DROP TABLE zones_legacy');
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    private static function tableHasColumn(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->query(sprintf('PRAGMA table_info(%s)', $table));

        foreach ($stmt->fetchAll() as $row) {
            if (($row['name'] ?? null) === $column) {
                return true;
            }
        }

        return false;
    }

    private static function ensureZonesIndexes(PDO $pdo): void
    {
        foreach ([
            'CREATE INDEX IF NOT EXISTS idx_zones_city ON zones (city)',
            'CREATE INDEX IF NOT EXISTS idx_zones_type ON zones (type)',
            'CREATE INDEX IF NOT EXISTS idx_zones_status ON zones (status)',
            'CREATE INDEX IF NOT EXISTS idx_zones_name ON zones (name)',
            'CREATE INDEX IF NOT EXISTS idx_zones_lat_lng ON zones (latitude, longitude)',
            'CREATE INDEX IF NOT EXISTS idx_zones_hourly_rate ON zones (hourly_rate_eur)',
            'CREATE INDEX IF NOT EXISTS idx_zones_city_type ON zones (city, type)',
            'CREATE INDEX IF NOT EXISTS idx_zones_city_status ON zones (city, status)',
            "CREATE UNIQUE INDEX IF NOT EXISTS idx_zones_source_identity ON zones (source_provider, source_external_id) WHERE source_external_id IS NOT NULL",
            'CREATE INDEX IF NOT EXISTS idx_zone_availability_sources_zone_priority ON zone_availability_sources (zone_id, priority)',
            'CREATE INDEX IF NOT EXISTS idx_zone_availability_sources_provider_external ON zone_availability_sources (provider, external_id)',
        ] as $sql) {
            $pdo->exec($sql);
        }
    }

    private static function readSqlFile(string $path): string
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Failed to read SQL file "%s".', $path));
        }

        return $contents;
    }
}
