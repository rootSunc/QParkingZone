<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/bootstrap.php';

$config = \ParkingZones\Config\AppConfig::fromEnvironment();
\ParkingZones\Infrastructure\Database::sqliteFile($config->databasePath, $config->autoSeed);

fwrite(STDOUT, sprintf("Initialized database at %s\n", $config->databasePath));
