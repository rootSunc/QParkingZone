<?php
declare(strict_types=1);

namespace ParkingZones\Infrastructure;

use PDO;
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

    public static function sqliteFile(string $path, bool $autoSeed = true): PDO
    {
        self::ensureDirectoryExists(dirname($path));

        $pdo = self::connect('sqlite:' . $path);

        self::initializeSqliteDatabase(
            $pdo,
            dirname(__DIR__, 2) . '/database/schema.sql',
            dirname(__DIR__, 2) . '/database/seed.sql',
            $autoSeed
        );

        return $pdo;
    }

    public static function initializeSqliteDatabase(
        PDO $pdo,
        string $schemaPath,
        string $seedPath,
        bool $autoSeed = true
    ): void {
        if (!self::zonesTableExists($pdo)) {
            $pdo->exec(self::readSqlFile($schemaPath));
        } elseif (!self::zonesTableHasExpectedSchema($pdo)) {
            self::migrateZonesTable($pdo, self::readSqlFile($schemaPath));
        }

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
            && str_contains($normalized, 'city text not null');
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
                    opening_hours
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
                    opening_hours
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
        $stmt = $pdo->query(sprintf("PRAGMA table_info(%s)", $table));

        foreach ($stmt->fetchAll() as $row) {
            if (($row['name'] ?? null) === $column) {
                return true;
            }
        }

        return false;
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
