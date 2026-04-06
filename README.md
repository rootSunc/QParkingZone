# QParking Zones

A small fullstack application for browsing parking zones across Helsinki, Espoo, and Vantaa.

Built with:
- **Backend:** PHP (Slim) + SQLite
- **Frontend:** Vue 3 (Composition API + TypeScript)
- **Extras:** Leaflet map, basic tests, Docker setup

## Repository Layout

```text
apps/
  api/         PHP API
  web/         Vue frontend
infra/
  docker/      Dockerfiles and docker-compose
```

## Features

- View all parking zones
- View detailed zone information
- Search, filter, sort, and paginate zones
- Map view with location pin
- Shareable URL state for city and catalog filters
- Responsive UI (mobile + desktop)
- REST API with proper status codes
- Basic tests for frontend and backend
- Docker setup for easy local running

## Getting Started

### Option 1: Run locally

#### 1. Start backend

```bash
cd apps/api
composer install
composer run db:init
php -S localhost:8000 -t public
```

Backend runs at [http://localhost:8000](http://localhost:8000)

#### 2. Start frontend

```bash
cd apps/web
npm install
npm run dev
```

Frontend runs at [http://localhost:5173](http://localhost:5173)

The frontend expects `VITE_API_BASE_URL` for the dev proxy. A local `.env` is already included:

```bash
VITE_API_BASE_URL=http://localhost:8000
```

### Option 2: Run with Docker

```bash
cd infra/docker
docker compose up --build
```

- Frontend: [http://localhost:5173](http://localhost:5173)
- Backend: [http://localhost:8000](http://localhost:8000)
- Docker frontend is served by `nginx` from built static assets, not by the Vite dev server

## API Endpoints

### GET /api/zones

Returns a paginated list of parking zone summaries.

Supported query params:

- `city=helsinki|espoo|vantaa`
- `q=<search text>`
- `type=<zone type>`
- `status=active|inactive`
- `sort=name|price_desc|price_asc`
- `page=<positive integer>`
- `limit=<positive integer>`

```json
{
  "items": [
    {
      "id": 1,
      "name": "Kamppi Center",
      "city": "helsinki",
      "type": "commercial",
      "status": "active",
      "hourlyRateEur": 4.5,
      "latitude": 60.1685,
      "longitude": 24.9318
    }
  ],
  "total": 5,
  "page": 1,
  "limit": 4
}
```

### GET /api/zones/{id}

Returns detailed information for a zone.

```json
{
  "id": 1,
  "name": "Kamppi Center",
  "type": "commercial",
  "status": "active",
  "description": "Underground parking facility...",
  "maxCapacity": 450,
  "hourlyRateEur": 4.5,
  "latitude": 60.1685,
  "longitude": 24.9318,
  "amenities": ["EV Charging", "Security Cameras"],
  "openingHours": {
    "weekdays": "06:00-23:00",
    "weekends": "08:00-23:00"
  }
}
```

## Testing

Frontend tests use **Vitest**.

```bash
cd apps/web
npm run test:unit
```

Backend tests use **PHPUnit** and run against an in-memory SQLite database.

```bash
cd apps/api
./vendor/bin/phpunit --configuration phpunit.xml
```

## Configuration

Backend environment variables:

- `APP_ENV=production|development` controls the default debug mode
- `APP_DEBUG=true|false` overrides debug output explicitly
- `PARKING_ZONES_DB_PATH=/absolute/path/to/zones.sqlite` changes the SQLite file location
- `PARKING_ZONES_AUTO_SEED=true|false` controls whether an empty database is seeded automatically

Frontend environment variables:

- `VITE_API_BASE_URL=http://localhost:8000` configures the Vite dev proxy target

## Tech Choices

- **Slim Framework (PHP)** for a lightweight REST API
- **SQLite** for simple local persistence
- **Vue 3 (Composition API)** for the frontend
- **TypeScript** for safer data contracts
- **Leaflet** for map rendering

## Known Limitations

- No authentication
- No real-time availability or occupancy feed
- No amenity or distance filtering yet
- Uses public OpenStreetMap tiles
- Docker setup is aimed at local development rather than production orchestration

## Future Improvements

- Real-time availability data
- Amenity and distance filtering
- Backend validation and schema improvements
- Structured opening-hours rules for `open now` support
