<?php
declare(strict_types=1);

use ParkingZones\ApplicationFactory;
use ParkingZones\Config\AppConfig;
use ParkingZones\Infrastructure\Database;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

final class ApiTest extends TestCase
{
    private App $app;
    private ServerRequestFactory $requestFactory;
    private DateTimeImmutable $currentTime;

    protected function setUp(): void
    {
        $this->currentTime = new DateTimeImmutable('2025-01-13T10:00:00+02:00');
        $pdo = Database::connect('sqlite::memory:');
        Database::initializeSqliteDatabase(
            $pdo,
            __DIR__ . '/../database/schema.sql',
            __DIR__ . '/../database/seed.sql'
        );

        $this->app = ApplicationFactory::create(
            $pdo,
            new AppConfig(
                debug: false,
                databasePath: __DIR__ . '/../var/zones.sqlite',
                autoSeed: true,
                accessLogEnabled: false
            ),
            $this->currentTime
        );
        $this->requestFactory = new ServerRequestFactory();
    }

    public function testGetZonesReturnsContractedSummaryList(): void
    {
        $response = $this->request('GET', '/api/zones');
        $data = $this->decodeJson($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(['items', 'total', 'page', 'limit'], array_keys($data));
        $this->assertIsArray($data['items']);
        $this->assertNotEmpty($data['items']);
        $this->assertGreaterThan(0, $data['total']);
        $this->assertSame(1, $data['page']);
        $this->assertSame(20, $data['limit']);
        $this->assertSame(
            ['id', 'name', 'city', 'type', 'status', 'hourlyRateEur', 'latitude', 'longitude', 'amenities', 'isOpen', 'availability'],
            array_keys($data['items'][0])
        );
        $this->assertIsBool($data['items'][0]['isOpen']);
        $this->assertSame(
            ['state', 'badge', 'detail', 'schedule'],
            array_keys($data['items'][0]['availability'])
        );
    }

    public function testGetZonesCanBeFilteredByCity(): void
    {
        $response = $this->request('GET', '/api/zones?city=espoo');
        $data = $this->decodeJson($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($data['items']);
        $this->assertSame(4, $data['total']);

        foreach ($data['items'] as $zone) {
            $this->assertSame('espoo', $zone['city']);
        }
    }

    public function testGetZonesSupportsSearchSortStatusAndPagination(): void
    {
        $response = $this->request('GET', '/api/zones?city=vantaa&q=park&status=active&sort=price_desc&page=1&limit=1');
        $data = $this->decodeJson($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(2, $data['total']);
        $this->assertSame(1, $data['page']);
        $this->assertSame(1, $data['limit']);
        $this->assertCount(1, $data['items']);
        $this->assertSame('Jumbo Retail Park Hall', $data['items'][0]['name']);
        $this->assertSame('vantaa', $data['items'][0]['city']);
    }

    public function testGetZonesCanFilterOpenNow(): void
    {
        $response = $this->request('GET', '/api/zones?city=helsinki&open_now=true');
        $data = $this->decodeJson($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(4, $data['total']);

        foreach ($data['items'] as $zone) {
            $this->assertSame('active', $zone['status']);
        }
    }

    public function testGetZonesCanFilterByAmenities(): void
    {
        $response = $this->request('GET', '/api/zones?city=espoo&amenities=EV%20Charging,Indoor%20Parking');
        $data = $this->decodeJson($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(3, $data['total']);

        foreach ($data['items'] as $zone) {
            $this->assertContains('EV Charging', $zone['amenities']);
            $this->assertContains('Indoor Parking', $zone['amenities']);
        }
    }

    public function testGetZoneFacetsReturnsCityScopedFilterMetadata(): void
    {
        $response = $this->request('GET', '/api/zones/facets?city=helsinki');
        $data = $this->decodeJson($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('helsinki', $data['city']);
        $this->assertSame([
            ['value' => 'commercial', 'count' => 3],
            ['value' => 'street', 'count' => 2],
        ], $data['types']);
        $this->assertSame([
            ['value' => 'active', 'count' => 4],
            ['value' => 'inactive', 'count' => 1],
        ], $data['statuses']);
        $this->assertContains(['value' => 'EV Charging', 'count' => 2], $data['amenities']);
        $this->assertContains(['value' => 'Indoor Parking', 'count' => 2], $data['amenities']);
    }

    public function testGetZoneFacetsRejectsUnsupportedCity(): void
    {
        $response = $this->request('GET', '/api/zones/facets?city=turku');
        $data = $this->decodeJson($response);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(['error' => 'Unsupported city. Use helsinki, espoo, or vantaa.'], $data);
    }

    public function testGetZonesCanSortByDistanceWhenCoordinatesAreProvided(): void
    {
        $response = $this->request('GET', '/api/zones?city=helsinki&sort=distance_asc&lat=60.1670&lng=24.9475');
        $data = $this->decodeJson($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Esplanadi Park', $data['items'][0]['name']);
        $this->assertArrayHasKey('distanceKm', $data['items'][0]);
        $this->assertEquals(0.0, $data['items'][0]['distanceKm']);
    }

    public function testGetZonesRejectsUnsupportedQueryValues(): void
    {
        $response = $this->request('GET', '/api/zones?city=turku');
        $data = $this->decodeJson($response);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(['error' => 'Unsupported city. Use helsinki, espoo, or vantaa.'], $data);
    }

    public function testGetZonesRequiresCoordinatePairs(): void
    {
        $response = $this->request('GET', '/api/zones?lat=60.1670&sort=distance_asc');
        $data = $this->decodeJson($response);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(['error' => 'lat and lng must be provided together.'], $data);
    }

    public function testGetZonesRejectsOversizedPageLimits(): void
    {
        $response = $this->request('GET', '/api/zones?limit=101');
        $data = $this->decodeJson($response);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(['error' => 'Query parameter "limit" must be no greater than 100.'], $data);
    }

    public function testHealthEndpointReturnsStatusAndZoneCount(): void
    {
        $response = $this->request('GET', '/api/health');
        $data = $this->decodeJson($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $data['status']);
        $this->assertSame('ok', $data['database']);
        $this->assertArrayNotHasKey('zones', $data);
        $this->assertArrayHasKey('checkedAt', $data);
        $this->assertNotFalse(DateTimeImmutable::createFromFormat(DATE_ATOM, $data['checkedAt']));
    }

    public function testResponsesIncludeRequestIdHeader(): void
    {
        $response = $this->request('GET', '/api/health', ['X-Request-Id' => 'test-request-123']);

        $this->assertSame('test-request-123', $response->getHeaderLine('X-Request-Id'));
    }

    public function testGetZoneByIdReturnsContractedDetailPayload(): void
    {
        $response = $this->request('GET', '/api/zones/1');
        $data = $this->decodeJson($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            [
                'id',
                'name',
                'city',
                'type',
                'status',
                'description',
                'maxCapacity',
                'hourlyRateEur',
                'latitude',
                'longitude',
                'amenities',
                'openingHours',
                'isOpen',
                'availability',
            ],
            array_keys($data)
        );
        $this->assertSame(1, $data['id']);
        $this->assertIsArray($data['amenities']);
        $this->assertSame(['weekdays', 'weekends'], array_keys($data['openingHours']));
        $this->assertIsBool($data['isOpen']);
        $this->assertSame(['state', 'badge', 'detail', 'schedule'], array_keys($data['availability']));
    }

    public function testGetMissingZoneReturns404(): void
    {
        $response = $this->request('GET', '/api/zones/999');
        $data = $this->decodeJson($response);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(['error' => 'Zone not found'], $data);
    }

    public function testInvalidStoredJsonReturns500JsonError(): void
    {
        $pdo = Database::connect('sqlite::memory:');
        $pdo->exec("
            CREATE TABLE zones (
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
                amenities TEXT NOT NULL,
                opening_hours TEXT NOT NULL
            )
        ");
        $pdo->exec(<<<'SQL'
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
            ) VALUES (
                1,
                'Broken Zone',
                'helsinki',
                'street',
                'active',
                'This row stores malformed JSON structures.',
                10,
                1.5,
                60.0,
                24.0,
                '{}',
                '[]'
            )
        SQL);

        $this->app = ApplicationFactory::create(
            $pdo,
            new AppConfig(
                debug: false,
                databasePath: __DIR__ . '/../var/zones.sqlite',
                autoSeed: true,
                accessLogEnabled: false
            ),
            $this->currentTime
        );

        $response = $this->request('GET', '/api/zones/1');
        $data = $this->decodeJson($response);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('Internal server error', $data['error']);
        $this->assertArrayHasKey('requestId', $data);
        $this->assertSame($data['requestId'], $response->getHeaderLine('X-Request-Id'));
    }

    private function request(string $method, string $path, array $headers = []): ResponseInterface
    {
        $request = $this->requestFactory->createServerRequest($method, $path);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $this->app->handle($request);
    }

    private function decodeJson(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
