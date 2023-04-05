<?php declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use DI\Container;
use Psr\Container\ContainerInterface;
use Dotenv\Dotenv;
use CalApi\SQLiteDB;
use CalApi\IAM;
use CalApi\Log;

use CalApi\Middleware\CoverageMiddleware;

use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\Driver\Selector;
use SebastianBergmann\CodeCoverage\CodeCoverage;


define('CAL_ROOT', __DIR__);
define('CAL_PUBLIC', CAL_ROOT . '/public');
define('CAL_API', CAL_PUBLIC . '/api');
define('BASE_API_PATH', '/api');


$dotenv = Dotenv::createImmutable(CAL_ROOT);
$dotenv->safeLoad();

$container = new Container([
    'coverage_filter_dir' => __DIR__ . '/src',
    CodeCoverage::class => function (ContainerInterface $c) {
        $filter = new Filter;
        $filter->includeDirectory($c->get('coverage_filter_dir'));
        foreach (CoverageMiddleware::EXCLUDE_FILES as $file) {
            Log::info($file);
            $filter->excludeFile($file);
        };

        $coverage = new CodeCoverage(
            (new Selector)->forLineCoverage($filter),
            $filter
        );
        return $coverage;
    },
    CoverageMiddleware::class => function (ContainerInterface $c) {
        if (file_exists(CoverageMiddleware::PERSISTENT_FILE)) {
            return unserialize(file_get_contents(CoverageMiddleware::PERSISTENT_FILE));
        } else {
            return (new CoverageMiddleware($c->get(CodeCoverage::class)));
        }
    },
    SQLiteDB::class => DI\autowire()
        ->constructorParameter('db_file', CAL_ROOT . '/' . $_ENV['DB_FILE'])
        ->constructorParameter('busy_timeout', 5000),
    IAM::class => DI\autowire()
        ->constructorParameter('admin_pwd', $_ENV['APP_ADMIN_PWD']),
]);

return $container;