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
    ): App
    {
        $config ??= AppConfig::fromEnvironment();
        $connection = $pdo ?? Database::sqliteFile($config->databasePath, $config->autoSeed);

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
            return $responder->respond($response, [
                'status' => 'ok',
                'database' => 'ok',
                'zones' => $repository->countZones(),
                'checkedAt' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM),
            ]);
        });

        $app->get('/api/zones', function (Request $request, Response $response) use ($repository, $responder, $queryParser): Response {
            try {
                $query = $queryParser->parse($request->getQueryParams());
            } catch (InvalidQueryParameter $exception) {
                return $responder->respond($response, ['error' => $exception->getMessage()], 400);
            }

            return $responder->respond($response, $repository->fetchAllSummaries($query));
        });

        $app->get('/api/zones/facets', function (Request $request, Response $response) use ($repository, $responder, $queryParser): Response {
            try {
                $city = $queryParser->parseCityFilter($request->getQueryParams());
            } catch (InvalidQueryParameter $exception) {
                return $responder->respond($response, ['error' => $exception->getMessage()], 400);
            }

            return $responder->respond($response, $repository->fetchFacets($city));
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
}
