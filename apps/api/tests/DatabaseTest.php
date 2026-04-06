<?php
declare(strict_types=1);

use ParkingZones\Infrastructure\Database;
use PHPUnit\Framework\TestCase;

final class DatabaseTest extends TestCase
{
    public function testSqliteFileCreatesSchemaAndSeedsData(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'parking-zones-db-');
        self::assertNotFalse($path);
        unlink($path);

        try {
            $pdo = Database::sqliteFile($path, true);
            $count = (int) $pdo->query('SELECT COUNT(*) FROM zones')->fetchColumn();

            self::assertSame(12, $count);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function testSqliteFileCanSkipSeeding(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'parking-zones-db-');
        self::assertNotFalse($path);
        unlink($path);

        try {
            $pdo = Database::sqliteFile($path, false);
            $count = (int) $pdo->query('SELECT COUNT(*) FROM zones')->fetchColumn();

            self::assertSame(0, $count);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function testSchemaRejectsInvalidJsonShapes(): void
    {
        $pdo = Database::connect('sqlite::memory:');
        Database::initializeSqliteDatabase(
            $pdo,
            __DIR__ . '/../database/schema.sql',
            __DIR__ . '/../database/seed.sql',
            false
        );

        $this->expectException(\PDOException::class);

        $pdo->exec(<<<'SQL'
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
                opening_hours
            ) VALUES (
                'Broken Zone',
                'helsinki',
                'street',
                'active',
                'Invalid shapes',
                10,
                1.5,
                60.0,
                24.0,
                '{}',
                '[]'
            )
        SQL);
    }

    public function testInitializeMigratesLegacyZonesTableToConstrainedSchema(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'parking-zones-legacy-db-');
        self::assertNotFalse($path);
        unlink($path);

        try {
            $pdo = Database::connect('sqlite:' . $path);
            $pdo->exec("
                CREATE TABLE zones (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    type TEXT NOT NULL,
                    status TEXT NOT NULL,
                    description TEXT NOT NULL,
                    max_capacity INTEGER NOT NULL,
                    hourly_rate_eur REAL NOT NULL,
                    latitude REAL NOT NULL,
                    longitude REAL NOT NULL,
                    amenities TEXT NOT NULL,
                    opening_hours TEXT NOT NULL
                )
            ");
            $pdo->exec(<<<'SQL'
                INSERT INTO zones (
                    id,
                    name,
                    type,
                    status,
                    description,
                    max_capacity,
                    hourly_rate_eur,
                    latitude,
                    longitude,
                    amenities,
                    opening_hours
                ) VALUES (
                    1,
                    'Legacy Zone',
                    'street',
                    'active',
                    'Migrated from legacy schema',
                    12,
                    2.0,
                    60.0,
                    24.0,
                    '["Metered"]',
                    '{"weekdays":"08:00-18:00","weekends":"10:00-16:00"}'
                )
            SQL);

            Database::initializeSqliteDatabase(
                $pdo,
                __DIR__ . '/../database/schema.sql',
                __DIR__ . '/../database/seed.sql',
                false
            );

            $schema = $pdo->query("
                SELECT sql
                FROM sqlite_master
                WHERE type = 'table' AND name = 'zones'
            ")->fetchColumn();
            $count = (int) $pdo->query('SELECT COUNT(*) FROM zones')->fetchColumn();

            self::assertIsString($schema);
            self::assertStringContainsString('json_valid(amenities)', strtolower($schema));
            self::assertStringContainsString('city text not null', strtolower($schema));
            self::assertSame(1, $count);
            self::assertSame('helsinki', $pdo->query('SELECT city FROM zones LIMIT 1')->fetchColumn());
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
}
