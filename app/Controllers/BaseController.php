<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Validation\ValidationException;
use App\Validation\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

abstract class BaseController
{
    protected function render(Request $request, Response $response, string $template, array $data = []): Response
    {
        $view = Twig::fromRequest($request);
        return $view->render($response, $template, $data);
    }

    protected function json(Response $response, mixed $data, int $status = 200): Response
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            $payload = '{"error":"No se pudo serializar la respuesta JSON"}';
            $status = 500;
        }

        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }

    /**
     * Validate request data
     *
     * @param array<string, mixed> $data
     * @param array<string, \Respect\Validation\Validatable> $rules
     * @return void
     * @throws ValidationException
     */
    protected function validate(array $data, array $rules): void
    {
        Validator::validate($data, $rules);
    }

    /**
     * Validate request data and return errors (non-throwing)
     *
     * @param array<string, mixed> $data
     * @param array<string, \Respect\Validation\Validatable> $rules
     * @return array<string, array<int, string>>
     */
    protected function check(array $data, array $rules): array
    {
        return Validator::check($data, $rules);
    }

    /**
     * Get parsed body from request
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    protected function getBody(Request $request): array
    {
        $body = $request->getParsedBody();
        return is_array($body) ? $body : [];
    }

    /**
     * Get input value from request (body or query)
     *
     * @param Request $request
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function input(Request $request, string $key, mixed $default = null): mixed
    {
        $body = $this->getBody($request);
        if (isset($body[$key])) {
            return $body[$key];
        }

        $query = $request->getQueryParams();
        return $query[$key] ?? $default;
    }

    /**
     * Return validation error response (for APIs)
     *
     * @param Response $response
     * @param ValidationException $e
     * @param int $status
     * @return Response
     */
    protected function validationError(Response $response, ValidationException $e, int $status = 422): Response
    {
        return $this->json($response, [
            'error' => 'Validation failed',
            'message' => $e->getMessage(),
            'errors' => $e->getErrors(),
        ], $status);
    }

    /**
     * Get CSRF protection from request
     *
     * @param Request $request
     * @return \App\Services\Csrf\CsrfProtection|null
     */
    protected function getCsrf(Request $request): ?\App\Services\Csrf\CsrfProtection
    {
        return $request->getAttribute('csrf');
    }

    /**
     * Get CSRF token name
     *
     * @param Request $request
     * @return string
     */
    protected function getCsrfTokenName(Request $request): string
    {
        $csrf = $this->getCsrf($request);
        return $csrf ? $csrf->getTokenName() : 'csrf_token';
    }

    /**
     * Get CSRF token value
     *
     * @param Request $request
     * @return string
     */
    protected function getCsrfTokenValue(Request $request): string
    {
        $csrf = $this->getCsrf($request);
        return $csrf ? $csrf->getTokenValue() : '';
    }

    /**
     * Get CSRF hidden input HTML
     *
     * @param Request $request
     * @return string
     */
    protected function getCsrfInput(Request $request): string
    {
        $csrf = $this->getCsrf($request);
        return $csrf ? $csrf->getTokenInput() : '';
    }

    /**
     * Return a 404 Not Found response
     *
     * @param Response $response
     * @param string|null $message Custom error message
     * @param array<string, mixed> $data Additional data
     * @return Response
     */
    protected function notFound(Response $response, ?string $message = null, array $data = []): Response
    {
        $error = [
            'error' => 'Not Found',
            'message' => $message ?? 'The requested resource was not found',
        ];

        return $this->json($response, array_merge($error, $data), 404);
    }

    /**
     * Return an error response
     *
     * @param Response $response
     * @param string $message Error message
     * @param int $status HTTP status code
     * @param array<string, mixed> $data Additional data
     * @return Response
     */
    protected function error(Response $response, string $message, int $status = 500, array $data = []): Response
    {
        $error = [
            'error' => $this->getErrorTitle($status),
            'message' => $message,
        ];

        return $this->json($response, array_merge($error, $data), $status);
    }

    /**
     * Get error title based on HTTP status code
     */
    private function getErrorTitle(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            422 => 'Validation Error',
            500 => 'Internal Server Error',
            default => 'Error',
        };
    }
}
