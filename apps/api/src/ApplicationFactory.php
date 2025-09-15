<?php
declare(strict_types=1);

namespace ParkingZones;

use ParkingZones\Config\AppConfig;
use ParkingZones\Http\JsonErrorHandler;
use ParkingZones\Http\JsonResponder;
use ParkingZones\Infrastructure\Database;
use ParkingZones\Repository\ZoneRepository;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;

final class ApplicationFactory
{
    public static function create(?PDO $pdo = null, ?AppConfig $config = null): App
    {
        $config ??= AppConfig::fromEnvironment();

        $app = SlimAppFactory::create();
        $responder = new JsonResponder();
        $errorMiddleware = $app->addErrorMiddleware($config->debug, true, true);
        $errorMiddleware->setDefaultErrorHandler(
            new JsonErrorHandler($app->getResponseFactory(), $responder)
        );

        $repository = new ZoneRepository(
            $pdo ?? Database::sqliteFile($config->databasePath, $config->autoSeed)
        );

        $app->get('/api/zones', function (Request $request, Response $response) use ($repository, $responder): Response {
            $city = $request->getQueryParams()['city'] ?? null;

            if (!is_string($city) || $city === '') {
                $city = null;
            } else {
                $city = strtolower($city);
            }

            return $responder->respond($response, $repository->fetchAllSummaries($city));
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
