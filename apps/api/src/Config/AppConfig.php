<?php
declare(strict_types=1);

namespace ParkingZones\Config;

final class AppConfig
{
    public function __construct(
        public readonly bool $debug,
        public readonly string $databasePath,
        public readonly bool $autoSeed,
        public readonly bool $liveAvailabilityEnabled = false,
        public readonly string $parkkihubiBaseUrl = 'https://pubapi.parkkiopas.fi/public/v1',
        public readonly string $liipiBaseUrl = 'https://parking.fintraffic.fi/api/v1',
        public readonly float $availabilityHttpTimeoutSeconds = 2.0
    ) {
    }

    public static function fromEnvironment(): self
    {
        $isProduction = self::stringEnv('APP_ENV', 'development') === 'production';

        return new self(
            debug: self::boolEnv('APP_DEBUG', !$isProduction),
            databasePath: self::stringEnv('PARKING_ZONES_DB_PATH', dirname(__DIR__, 2) . '/var/zones.sqlite'),
            autoSeed: self::boolEnv('PARKING_ZONES_AUTO_SEED', true),
            liveAvailabilityEnabled: self::boolEnv('PARKING_ZONES_ENABLE_LIVE_AVAILABILITY', false),
            parkkihubiBaseUrl: self::stringEnv('PARKING_ZONES_PARKKIHUBI_BASE_URL', 'https://pubapi.parkkiopas.fi/public/v1'),
            liipiBaseUrl: self::stringEnv('PARKING_ZONES_LIIPI_BASE_URL', 'https://parking.fintraffic.fi/api/v1'),
            availabilityHttpTimeoutSeconds: self::floatEnv('PARKING_ZONES_AVAILABILITY_HTTP_TIMEOUT', 2.0),
        );
    }

    private static function boolEnv(string $name, bool $default): bool
    {
        $value = getenv($name);

        if ($value === false || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    private static function stringEnv(string $name, string $default): string
    {
        $value = getenv($name);

        if ($value === false || $value === '') {
            return $default;
        }

        return $value;
    }

    private static function floatEnv(string $name, float $default): float
    {
        $value = getenv($name);

        if ($value === false || $value === '') {
            return $default;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);

        if (!is_float($normalized) && !is_int($normalized)) {
            return $default;
        }

        return $normalized > 0 ? (float) $normalized : $default;
    }
}
