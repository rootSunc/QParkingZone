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
    CHECK (json_valid(opening_hours) AND json_type(opening_hours) = 'object')
);
