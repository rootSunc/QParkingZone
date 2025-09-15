<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/bootstrap.php';

$config = \ParkingZones\Config\AppConfig::fromEnvironment();

error_reporting(E_ALL);
ini_set('display_errors', $config->debug ? '1' : '0');

$app = \ParkingZones\ApplicationFactory::create(null, $config);
$app->run();
