<?php
declare(strict_types=1);

namespace ParkingZones;

use ParkingZones\Config\AppConfig;
use ParkingZones\Http\JsonErrorHandler;
use ParkingZones\Http\JsonResponder;
use ParkingZones\Infrastructure\Database;
use ParkingZones\Repository\ZoneRepository;
use DateTimeImmutable;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;

final class ApplicationFactory
{
    private const MAX_PAGE_SIZE = 100;

    public static function create(
        ?PDO $pdo = null,
        ?AppConfig $config = null,
        ?DateTimeImmutable $currentTime = null
    ): App
    {
        $config ??= AppConfig::fromEnvironment();

        $app = SlimAppFactory::create();
        $responder = new JsonResponder();
        $errorMiddleware = $app->addErrorMiddleware($config->debug, true, true);
        $errorMiddleware->setDefaultErrorHandler(
            new JsonErrorHandler($app->getResponseFactory(), $responder)
        );

        $repository = new ZoneRepository(
            $pdo ?? Database::sqliteFile($config->databasePath, $config->autoSeed),
            $currentTime
        );

        $app->get('/api/zones', function (Request $request, Response $response) use ($repository, $responder): Response {
            $queryParams = $request->getQueryParams();

            return $responder->respond(
                $response,
                $repository->fetchAllSummaries(
                    self::readNormalizedString($queryParams['city'] ?? null),
                    self::readNormalizedString($queryParams['q'] ?? null),
                    self::readNormalizedString($queryParams['type'] ?? null),
                    self::readNormalizedString($queryParams['status'] ?? null),
                    self::readSummarySort($queryParams['sort'] ?? null),
                    self::readBoolean($queryParams['open_now'] ?? null, false),
                    self::readCoordinate($queryParams['lat'] ?? null, -90.0, 90.0),
                    self::readCoordinate($queryParams['lng'] ?? null, -180.0, 180.0),
                    self::readPositiveInteger($queryParams['page'] ?? null, 1),
                    self::readPositiveInteger($queryParams['limit'] ?? null, 20, self::MAX_PAGE_SIZE)
                )
            );
        });

        $app->get('/api/zones/{id}', function (Request $request, Response $response, array $args) use ($repository, $responder): Response {
            $zone = $repository->fetchDetailById((int) $args['id']);

            if ($zone === null) {
                return $responder->respond($response, ['error' => 'Zone not found'], 404);
            }

            return $responder->respond($response, $zone);
        });

        return $app;
    }

    private static function readNormalizedString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        return $normalized === '' ? null : $normalized;
    }

    private static function readSummarySort(mixed $value): string
    {
        if (!is_string($value)) {
            return 'name';
        }

        return match (trim($value)) {
            'price_asc', 'price_desc', 'distance_asc', 'name' => trim($value),
            default => 'name',
        };
    }

    private static function readBoolean(mixed $value, bool $default): bool
    {
        if (!is_string($value) && !is_bool($value) && !is_int($value)) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    private static function readCoordinate(mixed $value, float $minimum, float $maximum): ?float
    {
        if (!is_string($value) && !is_float($value) && !is_int($value)) {
            return null;
        }

        $coordinate = filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);

        if ($coordinate === null || $coordinate < $minimum || $coordinate > $maximum) {
            return null;
        }

        return (float) $coordinate;
    }

    private static function readPositiveInteger(mixed $value, int $default, ?int $maximum = null): int
    {
        if (!is_string($value) && !is_int($value)) {
            return $default;
        }

        $normalized = (int) $value;

        if ($normalized < 1) {
            return $default;
        }

        if ($maximum !== null && $normalized > $maximum) {
            return $maximum;
        }

        return $normalized;
    }
}
