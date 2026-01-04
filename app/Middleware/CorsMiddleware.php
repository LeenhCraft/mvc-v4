<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CorsMiddleware implements MiddlewareInterface
{
    /** @var array<int, string> */
    private array $allowedOrigins;

    private string $allowedMethods;
    private string $allowedHeaders;
    private int $maxAge;
    private bool $allowCredentials;

    /**
     * @param array{
     *   allowed_origins?: array<int, string>|string,
     *   allowed_methods?: string,
     *   allowed_headers?: string,
     *   max_age?: int,
     *   allow_credentials?: bool
     * } $config
     */
    public function __construct(array $config = [])
    {
        // Parse allowed origins
        $origins = $config['allowed_origins'] ?? ['*'];
        if (is_string($origins)) {
            $origins = array_filter(array_map('trim', explode(',', $origins)));
        }
        $this->allowedOrigins = $origins;

        $this->allowedMethods = (string)($config['allowed_methods'] ?? 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        $this->allowedHeaders = (string)($config['allowed_headers'] ?? 'X-Requested-With, Content-Type, Accept, Origin, Authorization');
        $this->maxAge = (int)($config['max_age'] ?? 86400); // 24 hours
        $this->allowCredentials = (bool)($config['allow_credentials'] ?? false);
    }

    public function process(Request $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Handle preflight OPTIONS request
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return $this->buildPreflightResponse($request);
        }

        // Handle actual request
        $response = $handler->handle($request);
        return $this->addCorsHeaders($request, $response);
    }

    private function buildPreflightResponse(Request $request): ResponseInterface
    {
        $response = new \Slim\Psr7\Response();
        return $this->addCorsHeaders($request, $response);
    }

    private function addCorsHeaders(Request $request, ResponseInterface $response): ResponseInterface
    {
        $origin = $this->getOrigin($request);
        $allowedOrigin = $this->getAllowedOrigin($origin);

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
            ->withHeader('Access-Control-Allow-Methods', $this->allowedMethods)
            ->withHeader('Access-Control-Allow-Headers', $this->allowedHeaders)
            ->withHeader('Access-Control-Max-Age', (string)$this->maxAge);

        if ($this->allowCredentials && $allowedOrigin !== '*') {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        // Expose headers for client-side access
        $response = $response->withHeader('Access-Control-Expose-Headers', 'Content-Length, Content-Type');

        return $response;
    }

    private function getOrigin(Request $request): string
    {
        return $request->getHeaderLine('Origin') ?: '';
    }

    private function getAllowedOrigin(string $requestOrigin): string
    {
        // If wildcard is allowed, return it
        if (in_array('*', $this->allowedOrigins, true)) {
            return '*';
        }

        // If no origin in request, return first allowed origin or deny
        if ($requestOrigin === '') {
            return $this->allowedOrigins[0] ?? 'null';
        }

        // Check if request origin is in whitelist
        foreach ($this->allowedOrigins as $allowed) {
            // Exact match
            if ($allowed === $requestOrigin) {
                return $requestOrigin;
            }

            // Wildcard subdomain match (e.g., *.example.com)
            if (str_starts_with($allowed, '*.')) {
                $domain = substr($allowed, 2);
                if (str_ends_with($requestOrigin, '.' . $domain) || $requestOrigin === 'https://' . $domain || $requestOrigin === 'http://' . $domain) {
                    return $requestOrigin;
                }
            }
        }

        // Origin not in whitelist, return first allowed origin
        return $this->allowedOrigins[0] ?? 'null';
    }
}
