<?php
declare(strict_types=1);

namespace ParkingZones;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use ParkingZones\Config\AppConfig;
use ParkingZones\Http\AccessLogMiddleware;
use ParkingZones\Http\InvalidQueryParameter;
use ParkingZones\Http\JsonErrorHandler;
use ParkingZones\Http\JsonResponder;
use ParkingZones\Http\RequestIdMiddleware;
use ParkingZones\Http\ZoneSummaryQueryParser;
use ParkingZones\Infrastructure\Database;
use ParkingZones\Repository\ZoneRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;

final class ApplicationFactory
{
    public static function create(
        ?PDO $pdo = null,
        ?AppConfig $config = null,
        ?DateTimeImmutable $currentTime = null
    ): App {
        $config ??= AppConfig::fromEnvironment();
        $connection = self::resolveConnection($pdo, $config, $currentTime);

        $app = SlimAppFactory::create();
        $responder = new JsonResponder();
        $errorMiddleware = $app->addErrorMiddleware($config->debug, true, true);
        $errorMiddleware->setDefaultErrorHandler(
            new JsonErrorHandler($app->getResponseFactory(), $responder)
        );
        if ($config->accessLogEnabled) {
            $app->add(new AccessLogMiddleware());
        }
        $app->add(new RequestIdMiddleware());

        $queryParser = new ZoneSummaryQueryParser();
        $repository = new ZoneRepository(
            $connection,
            $currentTime
        );

        $app->get('/api/health', function (Request $request, Response $response) use ($repository, $responder): Response {
            $repository->ping();

            return $responder->respond($response, [
                'status' => 'ok',
                'database' => 'ok',
                'checkedAt' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM),
            ], 200, [
                'Cache-Control' => 'no-store',
            ]);
        });

        $app->get('/api/zones', function (Request $request, Response $response) use ($repository, $responder, $queryParser): Response {
            try {
                $query = $queryParser->parse($request->getQueryParams());
            } catch (InvalidQueryParameter $exception) {
                return $responder->respond($response, ['error' => $exception->getMessage()], 400);
            }

            return $responder->respond(
                $response,
                $repository->fetchAllSummaries($query),
                200,
                ['Cache-Control' => 'public, max-age=30']
            );
        });

        $app->get('/api/zones/facets', function (Request $request, Response $response) use ($repository, $responder, $queryParser): Response {
            try {
                $city = $queryParser->parseCityFilter($request->getQueryParams());
            } catch (InvalidQueryParameter $exception) {
                return $responder->respond($response, ['error' => $exception->getMessage()], 400);
            }

            return $responder->respond(
                $response,
                $repository->fetchFacets($city),
                200,
                ['Cache-Control' => 'public, max-age=60']
            );
        });

        $app->get('/api/zones/{id}', function (Request $request, Response $response, array $args) use ($repository, $responder): Response {
            $zone = $repository->fetchDetailById((int) $args['id']);

            if ($zone === null) {
                return $responder->respond($response, ['error' => 'Zone not found'], 404);
            }

            return $responder->respond(
                $response,
                $zone,
                200,
                ['Cache-Control' => 'public, max-age=60']
            );
        });

        return $app;
    }

    private static function resolveConnection(
        ?PDO $pdo,
        AppConfig $config,
        ?DateTimeImmutable $currentTime
    ): PDO {
        if ($pdo !== null) {
            Database::configureSqliteConnection($pdo, $currentTime);

            return $pdo;
        }

        if (is_file($config->databasePath)) {
            return Database::connectSqliteFile($config->databasePath, $currentTime);
        }

        // First boot without an explicit init: create schema once, then request path stays connect-only.
        return Database::sqliteFile($config->databasePath, $config->autoSeed, $currentTime);
    }
}
