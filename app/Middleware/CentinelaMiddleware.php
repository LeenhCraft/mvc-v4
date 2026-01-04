<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\Centinela\CentinelaConfig;
use App\Services\Centinela\CentinelaDatabaseLogger;
use App\Services\Centinela\CentinelaFileLogger;
use App\Services\Centinela\CentinelaPayloadBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CentinelaMiddleware implements MiddlewareInterface
{
    private CentinelaConfig $config;
    private CentinelaFileLogger $fileLogger;
    private CentinelaDatabaseLogger $dbLogger;

    public function __construct(array $config = [])
    {
        $this->config = new CentinelaConfig($config);
        $this->fileLogger = new CentinelaFileLogger($this->config);
        $this->dbLogger = new CentinelaDatabaseLogger($this->config);
    }

    public function process(Request $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->config->isEnabled()) {
            return $handler->handle($request);
        }

        $requestId = CentinelaPayloadBuilder::generateRequestId();
        $start = microtime(true);

        $response = $handler->handle($request);

        $durationMs = (int)round((microtime(true) - $start) * 1000);

        $this->logRequest($request, $response, $requestId, $durationMs);

        return $response;
    }

    /**
     * Static method for logging from external code (e.g., CORS middleware)
     */
    public static function log(Request $request, ?ResponseInterface $response, array $config = []): void
    {
        $centinelaConfig = new CentinelaConfig($config);

        if (!$centinelaConfig->isEnabled()) {
            return;
        }

        $requestId = CentinelaPayloadBuilder::generateRequestId();
        $payload = CentinelaPayloadBuilder::build($request, $response, $centinelaConfig, $requestId);

        $fileLogger = new CentinelaFileLogger($centinelaConfig);
        $dbLogger = new CentinelaDatabaseLogger($centinelaConfig);

        $fileLogger->log($payload);
        $dbLogger->log($payload);
    }

    private function logRequest(Request $request, ResponseInterface $response, string $requestId, int $durationMs): void
    {
        $payload = CentinelaPayloadBuilder::build($request, $response, $this->config, $requestId, $durationMs);

        $this->fileLogger->log($payload);
        $this->dbLogger->log($payload);
    }
}
