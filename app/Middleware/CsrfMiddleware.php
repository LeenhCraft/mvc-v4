<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\Csrf\CsrfProtection;
use App\Services\Session\SessionManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CSRF Protection Middleware
 *
 * Validates CSRF tokens for state-changing requests
 */
class CsrfMiddleware implements MiddlewareInterface
{
    private SessionManager $session;
    private CsrfProtection $csrf;
    private bool $enabled;

    /** @var array<int, string> */
    private array $excludedPaths;

    /**
     * @param array{enabled?: bool, excluded_paths?: array<int, string>} $config
     */
    public function __construct(array $config = [])
    {
        $this->enabled = (bool)($config['enabled'] ?? true);
        $this->excludedPaths = $config['excluded_paths'] ?? [];

        $this->session = new SessionManager();
        $this->csrf = new CsrfProtection($this->session);
    }

    public function process(Request $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->enabled) {
            return $handler->handle($request);
        }

        // Add CSRF to request attributes for controllers
        $request = $request->withAttribute('csrf', $this->csrf);

        $method = strtoupper($request->getMethod());
        $path = $request->getUri()->getPath();

        // Only validate state-changing methods
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            // Check if path is excluded
            if ($this->isPathExcluded($path)) {
                return $handler->handle($request);
            }

            // Validate CSRF token
            if (!$this->validateRequest($request)) {
                return $this->failureResponse();
            }
        }

        return $handler->handle($request);
    }

    /**
     * Validate CSRF token from request
     */
    private function validateRequest(Request $request): bool
    {
        $token = $this->getTokenFromRequest($request);

        if (empty($token)) {
            return false;
        }

        return $this->csrf->validateToken($token);
    }

    /**
     * Get token from request (body or header)
     */
    private function getTokenFromRequest(Request $request): ?string
    {
        // Try to get from body first
        $body = $request->getParsedBody();
        if (is_array($body)) {
            $tokenName = $this->csrf->getTokenName();
            if (isset($body[$tokenName])) {
                return (string)$body[$tokenName];
            }
        }

        // Try to get from header (for AJAX requests)
        $headerName = 'X-CSRF-TOKEN';
        if ($request->hasHeader($headerName)) {
            return $request->getHeaderLine($headerName);
        }

        return null;
    }

    /**
     * Check if path is excluded from CSRF validation
     */
    private function isPathExcluded(string $path): bool
    {
        foreach ($this->excludedPaths as $excludedPath) {
            // Support wildcards by replacing * with a placeholder, then escaping, then replacing placeholder with .*
            $pattern = str_replace('__WILDCARD__', '.*', preg_quote(str_replace('*', '__WILDCARD__', $excludedPath), '/'));
            if (preg_match('/^' . $pattern . '$/', $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return failure response
     */
    private function failureResponse(): ResponseInterface
    {
        $response = new \Slim\Psr7\Response();

        $payload = json_encode([
            'error' => 'CSRF token validation failed',
            'message' => 'The CSRF token is missing or invalid. Please refresh the page and try again.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response->getBody()->write($payload);

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus(403);
    }

    /**
     * Get CSRF protection instance
     */
    public function getCsrf(): CsrfProtection
    {
        return $this->csrf;
    }
}
