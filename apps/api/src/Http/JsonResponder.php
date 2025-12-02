<?php
declare(strict_types=1);

namespace ParkingZones\Http;

use Psr\Http\Message\ResponseInterface as Response;

final class JsonResponder
{
    /**
     * @param array<string, string> $headers
     */
    public function respond(
        Response $response,
        mixed $data,
        int $status = 200,
        array $headers = []
    ): Response {
        $payload = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response->getBody()->write($payload);
        $response = $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
