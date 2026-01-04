<?php

declare(strict_types=1);

use App\Controllers\UserController;

/**
 * API Routes
 *
 * All routes here are prefixed with /api
 */

// Test route to verify API routing works
$app->get('/api/test', function ($request, $response) {
    $payload = json_encode(['message' => 'API is working!'], JSON_UNESCAPED_UNICODE);
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
});

// Test route to trigger 500 error (API)
$app->get('/api/test-error', function ($request, $response) {
    throw new \RuntimeException('This is a test error from the API');
});

// User endpoints
$app->group('/api/users', function ($group) {
    $group->get('', UserController::class . ':index');
    $group->get('/{id}', UserController::class . ':show');
    $group->post('', UserController::class . ':create');
    $group->put('/{id}', UserController::class . ':update');
    $group->delete('/{id}', UserController::class . ':delete');
    $group->post('/{id}/verify-email', UserController::class . ':verifyEmail');
});
