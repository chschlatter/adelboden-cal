<?php

use DI\Container;
use Dotenv\Dotenv;
use CalApi\SQLiteDB;
use CalApi\IAM;

require __DIR__ . '/vendor/autoload.php';

define('CAL_ROOT', __DIR__);
define('CAL_PUBLIC', CAL_ROOT . '/public');
define('CAL_API', CAL_PUBLIC . '/api');
define('BASE_API_PATH', '/api');


$dotenv = Dotenv::createImmutable(CAL_ROOT);
$dotenv->safeLoad();

$container = new Container([
    SQLiteDB::class => DI\autowire()
        ->constructorParameter('db_file', CAL_ROOT . '/' . $_ENV['DB_FILE'])
        ->constructorParameter('busy_timeout', 5000),
    IAM::class => DI\autowire()
        ->constructorParameter('admin_pwd', $_ENV['APP_ADMIN_PWD']),
]);

return $container;