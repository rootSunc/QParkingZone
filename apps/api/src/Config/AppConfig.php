<?php
declare(strict_types=1);

namespace ParkingZones\Config;

final class AppConfig
{
    public function __construct(
        public readonly bool $debug,
        public readonly string $databasePath,
        public readonly bool $autoSeed
    ) {
    }

    public static function fromEnvironment(): self
    {
        $isProduction = self::stringEnv('APP_ENV', 'development') === 'production';

        return new self(
            debug: self::boolEnv('APP_DEBUG', !$isProduction),
            databasePath: self::stringEnv('PARKING_ZONES_DB_PATH', dirname(__DIR__, 2) . '/var/zones.sqlite'),
            autoSeed: self::boolEnv('PARKING_ZONES_AUTO_SEED', true),
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
}
