<?php
declare(strict_types=1);

namespace ParkingZones\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

final class AccessLogMiddleware
{
    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $startedAt = microtime(true);

        try {
            $response = $handler->handle($request);
        } catch (Throwable $exception) {
            $this->log($request, 500, $startedAt, $exception);
            throw $exception;
        }

        $this->log($request, $response->getStatusCode(), $startedAt);

        return $response;
    }

    private function log(
        ServerRequestInterface $request,
        int $status,
        float $startedAt,
        ?Throwable $exception = null
    ): void {
        $payload = [
            'event' => 'http_request',
            'requestId' => RequestIdMiddleware::requestId($request),
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'status' => $status,
            'durationMs' => round((microtime(true) - $startedAt) * 1000, 2),
        ];

        if ($exception !== null) {
            $payload['exception'] = $exception::class;
            $payload['message'] = $exception->getMessage();
        }

        error_log(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}

