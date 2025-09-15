<?php
declare(strict_types=1);

namespace ParkingZones\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpException;
use Throwable;

final class JsonErrorHandler
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly JsonResponder $responder
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): ResponseInterface {
        $status = $this->resolveStatusCode($exception);
        $payload = [
            'error' => $this->resolveMessage($exception, $displayErrorDetails, $status),
        ];

        if ($displayErrorDetails) {
            $payload['exception'] = $exception::class;
        }

        return $this->responder->respond(
            $this->responseFactory->createResponse(),
            $payload,
            $status
        );
    }

    private function resolveStatusCode(Throwable $exception): int
    {
        if ($exception instanceof HttpException && $exception->getCode() >= 400) {
            return $exception->getCode();
        }

        return 500;
    }

    private function resolveMessage(Throwable $exception, bool $displayErrorDetails, int $status): string
    {
        if ($status >= 500 && !$displayErrorDetails) {
            return 'Internal server error';
        }

        if ($exception instanceof HttpException) {
            return $exception->getDescription() !== ''
                ? $exception->getDescription()
                : $exception->getMessage();
        }

        return $exception->getMessage() !== ''
            ? $exception->getMessage()
            : 'Internal server error';
    }
}
