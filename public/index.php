<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . '/storage/php-error.log');
error_reporting(E_ALL);
ini_set('memory_limit', '512M');
set_time_limit(60);

define('BASE_PATH', dirname(__DIR__));

session_start();

require BASE_PATH . '/vendor/autoload.php';

$app = require BASE_PATH . '/bootstrap/app.php';

$app->run();