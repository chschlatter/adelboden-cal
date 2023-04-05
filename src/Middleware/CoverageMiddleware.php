<?php declare(strict_types=1);

namespace CalApi\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Report\Html\Facade as Html;

use CalApi\Log;

class CoverageMiddleware implements MiddlewareInterface
{
    const HEADER = 'x-coverage-id';
    const PERSISTENT_FILE = '/tmp/CoverageMiddleware.serialized';
    const EXCLUDE_FILES = [
        __DIR__ . '/../CalApiCLI.php',
        __DIR__ . '/../CalApiClient.php',
        __DIR__ . '/../Log.php',
        __DIR__ . '/CoverageMiddleware.php'
    ];

    private CodeCoverage $coverage;
    private bool $removed_persistence = false;

    public function __construct(CodeCoverage $coverage)
    {
        $this->coverage = $coverage;
    }

    public function __destruct()
    {
        if (!$this->removed_persistence) {
            file_put_contents(self::PERSISTENT_FILE, serialize($this));
        }
    }

    public function removePersistence()
    {
        Log::info('removing ' . self::PERSISTENT_FILE);
        unlink(self::PERSISTENT_FILE);
        $this->removed_persistence = true;
    }

    public function htmlReport(string $path)
    {
        Log::info('htmlReport', $this->coverage->getTests());
        (new Html())->process($this->coverage, $path);
    }

    public function process(ServerRequestInterface $request, 
                            RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->hasHeader(self::HEADER)) {
            Log::info('CoverageMiddleware got header ' . self::HEADER . ': ' . $request->getHeaderLine(self::HEADER));
            $this->coverage->start($request->getHeaderLine(self::HEADER));
            try {
                return $handler->handle($request);
            } finally {
                $this->coverage->stop();
            }
        } else {
            return $handler->handle($request);
        }
    }
}
