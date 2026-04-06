# QParking Zones

QParking Zones is a lightweight full-stack demo for exploring parking zones across Helsinki, Espoo, and Vantaa. The frontend is a Vue 3 single-page application, the backend is a Slim 4 JSON API, and the data layer is SQLite with seeded sample data by default.

The repository is set up for local development, demos, and small self-hosted deployments. It is intentionally simple and not positioned as a production-hardened parking platform.

## What the project actually does

- Browse parking zones by city.
- Search zones by name.
- Filter the catalog by zone type, amenities, and `open now`.
- Use browser geolocation to sort by nearest zone and optionally limit results by radius.
- Keep catalog state in the URL query string so links are shareable.
- Open a detail page with pricing, capacity, opening hours, amenities, coordinates, and an embedded Leaflet map.
- Serve the same data through a small JSON API with frontend and backend tests.

## Current scope

- The default dataset is seeded demo data stored in SQLite.
- There is no authentication, reservation flow, payment flow, or admin UI.
- There is no list-page map view; the interactive map exists on the zone detail page only.
- Live occupancy or external availability integrations are not wired into the current API routes.

## Stack

- Frontend: Vue 3, TypeScript, Vite, Vue Router, Leaflet
- Backend: PHP 8.3, Slim 4, SQLite
- Infra: Docker, Docker Compose, Nginx, optional Caddy for HTTPS

## Repository layout

```text
apps/
  api/         PHP API, SQLite schema/seed, scripts, PHPUnit tests
  web/         Vue app, API client, Vitest tests
infra/
  caddy/       HTTPS reverse-proxy config
  docker/      Dockerfiles, Compose files, deployment notes
```

## Quick start

### Local development without Docker

Requirements:

- PHP 8.3+
- Composer
- Node.js 20+
- npm

Start the backend:

```bash
cd apps/api
composer install
composer run db:init
php -S localhost:8000 -t public
```

Start the frontend:

```bash
cd apps/web
npm install
npm run dev
```

Optional frontend env file:

```bash
cd apps/web
cp .env.example .env
```

Default local URLs:

- Frontend: `http://localhost:5173/#/`
- API: `http://localhost:8000/api/zones`

`VITE_API_BASE_URL` is only used by the Vite dev server proxy. If your backend is not running on `http://localhost:8000`, update `apps/web/.env`.

### Local development with Docker

```bash
docker compose -f infra/docker/docker-compose.yml up --build
```

Docker URLs:

- Frontend: `http://localhost:5173/#/`
- API: `http://localhost:8000/api/zones`

This Compose setup builds the frontend into static assets and serves them with Nginx. For hot reload and faster UI iteration, use the non-Docker workflow above.

### Production-style Docker run

```bash
docker compose -f infra/docker/docker-compose.prod.yml up -d --build
```

Default URL:

- Frontend: `http://localhost/#/`

The production Compose file keeps the backend private inside Docker and stores SQLite in a named volume.

### HTTPS deployment with a real domain

```bash
cp infra/docker/.env.https.example infra/docker/.env.https
```

Set the real domain values in `infra/docker/.env.https`:

```bash
DOMAIN=example.com
WWW_DOMAIN=www.example.com
```

`WWW_DOMAIN` is optional. Leave it empty if you only want a single hostname such as `qparking.example.com`.

Then start the HTTPS stack:

```bash
docker compose \
  --env-file infra/docker/.env.https \
  -f infra/docker/docker-compose.https.yml \
  up -d --build
```

The app will be served from `https://example.com/#/`.

For a full single-server rollout example, see `infra/docker/DEPLOY_HETZNER.md`.

## Data and database

The default database path is `apps/api/var/zones.sqlite`. Running `composer run db:init` creates the schema and seeds the database with sample zones for Helsinki, Espoo, and Vantaa.

An optional import script can replace the seeded data with parking records fetched from the Overpass API:

```bash
cd apps/api
php scripts/import-zones.php
```

### Real parking data: source and coverage

The real-data import path currently uses OpenStreetMap data through the Overpass API. The script sends a query for `amenity=parking` objects inside a broad bounding box that covers the Helsinki, Espoo, and Vantaa area:

- south-west / north-east bounds: `60.1, 24.6, 60.35, 25.15`
- object types: both `node` and `way`
- geometry handling: points use their own coordinates, and polygonal parking areas use the `center` returned by Overpass

In practice, this means the import covers real parking objects that exist in OSM within that region, rather than only the hand-written demo seed rows.

### What is real vs. what is derived

When `php scripts/import-zones.php` runs, the most reliable real fields are:

- parking object presence inside the Helsinki-region query bounds
- latitude and longitude
- OSM-provided `name` when available
- tagged `capacity` when available
- some parking characteristics exposed through OSM tags such as `parking`, `fee`, `charge`, `access`, `surface`, and `park_ride`

Some fields in the app schema are still derived or simplified during import:

- `city` is inferred from rough coordinate rules, not administrative polygons
- `type` is mapped heuristically from the OSM `parking` tag
- `amenities` are translated from a subset of OSM tags into UI-friendly labels
- `openingHours` are defaulted to simple schedules
- `hourlyRateEur` is simulated when the source only indicates that parking is paid
- `status` is currently assumed to be `active`
- `description` is generated text that includes the imported OSM element ID

Two amenities, `EV Charging` and `Security Cameras`, are currently assigned with random demo logic in the importer, so they should not be treated as authoritative real-world facts.

### Data integration approach

The integration is currently a one-shot import into the app's SQLite database:

1. The script initializes the local database schema.
2. It requests raw parking data from Overpass API.
3. It clears the existing `zones` table.
4. It transforms the external OSM payload into the app's internal `zones` schema.
5. The frontend and API then read only from local SQLite.

This keeps the runtime app simple because the UI does not call Overpass directly and the backend does not depend on live third-party requests for normal reads. It also means imported data is a snapshot, not a continuous sync.

### Suggested next optimizations

- Replace rough city assignment with polygon-based reverse geocoding or authoritative municipal boundaries.
- Store source metadata explicitly, for example `source`, external object ID, import timestamp, and raw source tags.
- Remove random demo enrichment and replace it with deterministic mapping rules only.
- Parse real `opening_hours` tags when present instead of applying fallback schedules.
- Improve paid-parking handling by extracting pricing from structured sources instead of generating placeholder hourly rates.
- Add deduplication and upsert logic so repeated imports update records instead of deleting and reloading the whole table.
- Add scheduled sync jobs plus import reporting for failures, skipped rows, and transformed fields.
- Combine OSM geometry with more authoritative city or operator datasets if you need better coverage, pricing accuracy, occupancy, or status changes.

The current importer is therefore useful for bootstrapping a more realistic demo dataset, but it should still be treated as a lightweight data-ingestion prototype rather than a production-grade parking data pipeline.

## Configuration

### Backend environment variables

- `APP_ENV=development|production`
- `APP_DEBUG=true|false`
- `PARKING_ZONES_DB_PATH=/absolute/path/to/zones.sqlite`
- `PARKING_ZONES_AUTO_SEED=true|false`

Additional config keys exist in `apps/api/src/Config/AppConfig.php`:

- `PARKING_ZONES_ENABLE_LIVE_AVAILABILITY`
- `PARKING_ZONES_PARKKIHUBI_BASE_URL`
- `PARKING_ZONES_LIIPI_BASE_URL`
- `PARKING_ZONES_AVAILABILITY_HTTP_TIMEOUT`

Those values are currently not connected to the exposed API routes.

### Frontend environment variables

- `VITE_API_BASE_URL=http://localhost:8000`

This value is used only by the Vite development proxy.

## API

### `GET /api/zones`

Returns a paginated list of zone summaries.

Supported query parameters:

- `city=helsinki|espoo|vantaa`
- `q=<search text>`
- `type=<zone type>`
- `status=active|inactive`
- `open_now=true|false`
- `lat=<latitude>`
- `lng=<longitude>`
- `radius=<positive float>`
- `amenities=<comma-separated list>`
- `sort=name|price_asc|price_desc|distance_asc`
- `page=<positive integer>`
- `limit=<positive integer>` with a backend maximum of `100`

Behavior notes:

- `open_now` is derived from stored opening hours in the `Europe/Helsinki` time zone.
- `distance_asc` sorting and `distanceKm` in the response are only meaningful when both `lat` and `lng` are provided.
- `amenities` matches zones that contain all requested amenities.

Example response:

```json
{
  "items": [
    {
      "id": 1,
      "name": "Kamppi Center",
      "city": "helsinki",
      "type": "commercial",
      "status": "active",
      "hourlyRateEur": 4.8,
      "latitude": 60.1685,
      "longitude": 24.9318,
      "amenities": ["EV Charging", "Indoor Parking"],
      "openingHours": {
        "weekdays": "00:00-23:59",
        "weekends": "00:00-23:59"
      },
      "distanceKm": 1.24
    }
  ],
  "total": 5,
  "page": 1,
  "limit": 20
}
```

### `GET /api/zones/{id}`

Returns full detail for a single zone, including:

- `description`
- `maxCapacity`
- `amenities`
- `openingHours`
- `latitude` and `longitude`

If the zone does not exist, the API returns `404` with:

```json
{
  "error": "Zone not found"
}
```

## Testing

Backend:

```bash
cd apps/api
composer test
```

Frontend:

```bash
cd apps/web
npm run test:run
```

Useful frontend checks:

```bash
cd apps/web
npm run lint
npm run build
```

## Deployment notes

The repository already includes separate Docker Compose files for local, production-style, and HTTPS deployments, but the operational model is still intentionally simple.

Known limitations:

- The backend container still uses PHP's built-in server.
- There are no health checks, readiness probes, or centralized observability.
- There is no secrets-management, backup, or restore workflow in-repo.
- There is no authentication, authorization, or rate limiting.
- SQLite is fine for a small demo deployment, but it is not the right long-term choice for higher concurrency.
