CREATE TABLE IF NOT EXISTS zones (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  city TEXT NOT NULL,
  type TEXT NOT NULL,
  status TEXT NOT NULL,
  description TEXT NOT NULL,
  max_capacity INTEGER NOT NULL,
  hourly_rate_eur REAL NOT NULL,
  latitude REAL NOT NULL,
  longitude REAL NOT NULL,
  amenities TEXT NOT NULL
    CHECK (json_valid(amenities) AND json_type(amenities) = 'array'),
  opening_hours TEXT NOT NULL
    CHECK (json_valid(opening_hours) AND json_type(opening_hours) = 'object'),
  source_provider TEXT NOT NULL DEFAULT 'seed',
  source_external_id TEXT,
  source_updated_at TEXT,
  source_payload TEXT NOT NULL DEFAULT '{}'
    CHECK (json_valid(source_payload) AND json_type(source_payload) = 'object')
);

CREATE TABLE IF NOT EXISTS zone_availability_sources (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  zone_id INTEGER NOT NULL,
  provider TEXT NOT NULL,
  external_id TEXT NOT NULL,
  priority INTEGER NOT NULL DEFAULT 1,
  metadata TEXT NOT NULL DEFAULT '{}'
    CHECK (json_valid(metadata) AND json_type(metadata) = 'object'),
  UNIQUE(zone_id, provider, external_id),
  FOREIGN KEY(zone_id) REFERENCES zones(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_zones_city ON zones (city);
CREATE INDEX IF NOT EXISTS idx_zones_type ON zones (type);
CREATE INDEX IF NOT EXISTS idx_zones_status ON zones (status);
CREATE INDEX IF NOT EXISTS idx_zones_name ON zones (name);
CREATE INDEX IF NOT EXISTS idx_zones_lat_lng ON zones (latitude, longitude);
CREATE INDEX IF NOT EXISTS idx_zones_hourly_rate ON zones (hourly_rate_eur);
CREATE INDEX IF NOT EXISTS idx_zones_city_type ON zones (city, type);
CREATE INDEX IF NOT EXISTS idx_zones_city_status ON zones (city, status);
CREATE UNIQUE INDEX IF NOT EXISTS idx_zones_source_identity
  ON zones (source_provider, source_external_id)
  WHERE source_external_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_zone_availability_sources_zone_priority
  ON zone_availability_sources (zone_id, priority);
CREATE INDEX IF NOT EXISTS idx_zone_availability_sources_provider_external
  ON zone_availability_sources (provider, external_id);
