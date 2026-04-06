<?php
declare(strict_types=1);

namespace ParkingZones\Http;

use Psr\Http\Message\ResponseInterface as Response;

final class JsonResponder
{
    public function respond(Response $response, mixed $data, int $status = 200): Response
    {
        $payload = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response->getBody()->write($payload);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
