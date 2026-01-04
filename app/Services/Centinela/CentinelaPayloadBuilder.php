<?php

declare(strict_types=1);

namespace App\Services\Centinela;

use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;

final class CentinelaPayloadBuilder
{
    public static function build(
        Request $request,
        ?ResponseInterface $response,
        CentinelaConfig $config,
        string $requestId,
        ?int $durationMs = null
    ): array {
        $rawBody = self::safeReadBody($request, $config->getMaxBodyBytes());
        $decodedBody = self::decodeBody($request, $rawBody);

        $uri = $request->getUri();
        $serverParams = $request->getServerParams();

        $headers = self::normalizeHeaders($request->getHeaders(), $config->getRedactHeaders());
        $responseHeaders = $response ? self::normalizeHeaders($response->getHeaders(), $config->getRedactResponseHeaders()) : null;
        $uploadedFiles = self::normalizeUploadedFiles($request->getUploadedFiles());
        $routeInfo = self::extractRouteInfo($request);

        return [
            'id' => $requestId,
            'timestamp' => (new DateTimeImmutable())->format('Y-m-d H:i:s.u'),
            'request' => [
                'method' => $request->getMethod(),
                'uri' => (string)$uri,
                'path' => $uri->getPath(),
                'query' => $uri->getQuery(),
                'query_params' => $request->getQueryParams(),
                'ip' => (string)($serverParams['REMOTE_ADDR'] ?? ''),
                'user_agent' => (string)($request->getHeaderLine('User-Agent') ?? ''),
                'content_type' => (string)($request->getHeaderLine('Content-Type') ?? ''),
                'content_length' => (string)($request->getHeaderLine('Content-Length') ?? ''),
                'headers' => $headers,
                'body' => $rawBody,
                'decoded_body' => self::normalizeParsedBody($decodedBody),
                'uploaded_files' => $uploadedFiles,
                'route' => $routeInfo,
            ],
            'response' => $response ? [
                'status' => $response->getStatusCode(),
                'headers' => $responseHeaders,
            ] : null,
            'meta' => [
                'duration_ms' => $durationMs,
            ],
        ];
    }

    public static function generateRequestId(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable) {
            return uniqid('centinela_', true);
        }
    }

    /**
     * @param array<string, array<int, string>> $headers
     * @param array<string, true> $redactMap
     * @return array<string, array<int, string>>
     */
    private static function normalizeHeaders(array $headers, array $redactMap): array
    {
        $out = [];
        foreach ($headers as $name => $values) {
            $key = strtolower((string)$name);
            if (isset($redactMap[$key])) {
                $out[(string)$name] = ['[REDACTED]'];
                continue;
            }
            $out[(string)$name] = array_values($values);
        }
        return $out;
    }

    private static function extractRouteInfo(Request $request): ?array
    {
        try {
            $routeContext = RouteContext::fromRequest($request);
            $route = $routeContext->getRoute();
            if ($route !== null) {
                return [
                    'name' => $route->getName(),
                    'pattern' => $route->getPattern(),
                    'methods' => $route->getMethods(),
                    'arguments' => $route->getArguments(),
                ];
            }
        } catch (\Throwable) {
        }
        return null;
    }

    private static function safeReadBody(Request $request, int $maxBytes): ?string
    {
        $contentType = strtolower($request->getHeaderLine('Content-Type'));
        if (str_contains($contentType, 'multipart/form-data') || str_contains($contentType, 'application/octet-stream')) {
            return '[BODY OMITIDO POR CONTENT-TYPE]';
        }

        $body = $request->getBody();
        if (!$body->isReadable()) {
            return null;
        }

        try {
            if ($body->isSeekable()) {
                $body->rewind();
            }

            $data = $body->getContents();

            if ($body->isSeekable()) {
                $body->rewind();
            }

            if ($maxBytes > 0 && strlen($data) > $maxBytes) {
                return substr($data, 0, $maxBytes) . '...[TRUNCADO]';
            }

            return $data;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function decodeBody(Request $request, ?string $rawBody): mixed
    {
        if ($rawBody === null) {
            return null;
        }

        $contentType = strtolower($request->getHeaderLine('Content-Type'));

        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($rawBody, true);
            return (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
        }

        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            $out = [];
            parse_str($rawBody, $out);
            return $out;
        }

        return null;
    }

    private static function normalizeParsedBody(mixed $parsedBody): mixed
    {
        if (is_array($parsedBody)) {
            return $parsedBody;
        }

        if (is_object($parsedBody)) {
            if ($parsedBody instanceof \JsonSerializable) {
                return $parsedBody;
            }
            return ['_object' => get_class($parsedBody)];
        }

        if (is_string($parsedBody) || is_int($parsedBody) || is_float($parsedBody) || is_bool($parsedBody) || $parsedBody === null) {
            return $parsedBody;
        }

        return null;
    }

    private static function normalizeUploadedFiles(array $files): array
    {
        $out = [];
        foreach ($files as $key => $file) {
            if (is_array($file)) {
                $out[$key] = self::normalizeUploadedFiles($file);
                continue;
            }

            $out[$key] = [
                'client_filename' => $file->getClientFilename(),
                'client_media_type' => $file->getClientMediaType(),
                'size' => $file->getSize(),
                'error' => $file->getError(),
            ];
        }

        return $out;
    }
}
