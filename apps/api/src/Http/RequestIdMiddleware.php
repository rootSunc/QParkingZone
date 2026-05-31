<?php
declare(strict_types=1);

namespace ParkingZones\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RequestIdMiddleware
{
    public const ATTRIBUTE = 'request_id';
    private const HEADER = 'X-Request-Id';

    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestId = $this->resolveRequestId($request);
        $request = $request->withAttribute(self::ATTRIBUTE, $requestId);

        return $handler
            ->handle($request)
            ->withHeader(self::HEADER, $requestId);
    }

    public static function requestId(ServerRequestInterface $request): ?string
    {
        $requestId = $request->getAttribute(self::ATTRIBUTE);

        return is_string($requestId) && $requestId !== '' ? $requestId : null;
    }

    private function resolveRequestId(ServerRequestInterface $request): string
    {
        $headerValue = trim($request->getHeaderLine(self::HEADER));

        if ($headerValue !== '' && preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $headerValue) === 1) {
            return $headerValue;
        }

        return bin2hex(random_bytes(16));
    }
}

