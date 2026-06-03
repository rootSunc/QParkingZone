<?php
declare(strict_types=1);

use ParkingZones\Infrastructure\Database;
use PHPUnit\Framework\TestCase;

final class ImportZonesScriptTest extends TestCase
{
    public function testFailedOverpassFetchLeavesSeedDataInEmptyDatabaseWhenAutoSeedIsEnabled(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'parking-zones-import-');
        self::assertNotFalse($path);
        unlink($path);

        try {
            $script = __DIR__ . '/../scripts/import-zones.php';
            $command = sprintf(
                'PARKING_ZONES_DB_PATH=%s PARKING_ZONES_AUTO_SEED=true PARKING_ZONES_OVERPASS_URLS=%s php %s 2>&1',
                escapeshellarg($path),
                escapeshellarg('http://127.0.0.1:9/api/interpreter'),
                escapeshellarg($script)
            );

            exec($command, $output, $exitCode);

            self::assertSame(1, $exitCode, implode("\n", $output));
            self::assertStringContainsString('Initialized database at', implode("\n", $output));
            self::assertStringContainsString('Import failed before database changes', implode("\n", $output));

            $pdo = Database::connect('sqlite:' . $path);
            $count = (int) $pdo->query('SELECT COUNT(*) FROM zones')->fetchColumn();
            $seedCount = (int) $pdo->query("SELECT COUNT(*) FROM zones WHERE source_provider = 'seed'")->fetchColumn();

            self::assertSame(12, $count);
            self::assertSame(12, $seedCount);
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
}
