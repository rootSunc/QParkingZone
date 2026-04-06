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
            new AppConfig(false, __DIR__ . '/../var/zones.sqlite', true),
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
            ['id', 'name', 'city', 'type', 'status', 'hourlyRateEur', 'latitude', 'longitude', 'amenities', 'openingHours'],
            array_keys($data['items'][0])
        );
        $this->assertSame(['weekdays', 'weekends'], array_keys($data['items'][0]['openingHours']));
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

    public function testGetZonesCanSortByDistanceWhenCoordinatesAreProvided(): void
    {
        $response = $this->request('GET', '/api/zones?city=helsinki&sort=distance_asc&lat=60.1670&lng=24.9475');
        $data = $this->decodeJson($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Esplanadi Park', $data['items'][0]['name']);
        $this->assertArrayHasKey('distanceKm', $data['items'][0]);
        $this->assertEquals(0.0, $data['items'][0]['distanceKm']);
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
            ],
            array_keys($data)
        );
        $this->assertSame(1, $data['id']);
        $this->assertIsArray($data['amenities']);
        $this->assertSame(['weekdays', 'weekends'], array_keys($data['openingHours']));
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
            new AppConfig(false, __DIR__ . '/../var/zones.sqlite', true),
            $this->currentTime
        );

        $response = $this->request('GET', '/api/zones/1');
        $data = $this->decodeJson($response);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(['error' => 'Internal server error'], $data);
    }

    private function request(string $method, string $path): ResponseInterface
    {
        $request = $this->requestFactory->createServerRequest($method, $path);

        return $this->app->handle($request);
    }

    private function decodeJson(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
