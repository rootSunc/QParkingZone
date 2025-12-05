<?php
declare(strict_types=1);

namespace ParkingZones\Import;

use PDO;
use Throwable;

final class ZoneUpsertImporter
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param list<array<string, mixed>> $zones
     */
    public function import(array $zones): int
    {
        $this->pdo->beginTransaction();

        try {
            $this->pdo->exec('CREATE TEMP TABLE current_import_sources (source_external_id TEXT PRIMARY KEY)');
            $seenStmt = $this->pdo->prepare('INSERT INTO current_import_sources (source_external_id) VALUES (:source_external_id)');
            $upsertStmt = $this->pdo->prepare("
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

            $this->pdo->exec("DELETE FROM zones WHERE source_provider = 'seed'");

            foreach ($zones as $zone) {
                $seenStmt->execute(['source_external_id' => $zone['source_external_id']]);
                $upsertStmt->execute($zone);
            }

            $this->pdo->exec("
                DELETE FROM zones
                WHERE source_provider = 'openstreetmap'
                  AND source_external_id NOT IN (
                    SELECT source_external_id
                    FROM current_import_sources
                  )
            ");
            $this->pdo->exec('DROP TABLE current_import_sources');
            $this->pdo->commit();

            return count($zones);
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }
}
