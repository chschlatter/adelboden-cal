<?php declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

define('CAL_ROOT', dirname(__DIR__, 1));

use CalApi\CalApiClient;

$dotenv = Dotenv\Dotenv::createImmutable(CAL_ROOT);
$dotenv->safeLoad();

$client = new CalApiClient('http://localhost/api/', $_ENV['APP_ADMIN_PWD']);

register_shutdown_function(function () use ($client): void {
    $client->sendRequest('GET', 'api-tests/coverage-report', [], 'admin');
});