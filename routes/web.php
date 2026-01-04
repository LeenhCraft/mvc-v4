<?php

declare(strict_types=1);

use App\Controllers\FormController;
use App\Controllers\HomeController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

// $app->get('/', function (Request $request, Response $response): Response {
//     $controller = new HomeController();
//     return $controller->index($request, $response, []);
// });

$app->get('/', HomeController::class . ':index');

$app->get('/welcome/{name}', function (Request $request, Response $response, array $args): Response {
    $name = (string)($args['name'] ?? 'Invitado');

    $view = Twig::fromRequest($request);
    return $view->render($response, 'welcome.twig', [
        'title' => 'Bienvenido a Windsurf MVC',
        'name' => $name,
    ]);
});

// Form routes with CSRF protection
$app->get('/contact', FormController::class . ':showContactForm');
$app->post('/contact', FormController::class . ':submitContactForm');

$app->get('/register', FormController::class . ':showRegisterForm');
$app->post('/register', FormController::class . ':submitRegisterForm');

// Test route to trigger 500 error (Web)
$app->get('/test-error', function (Request $request, Response $response) {
    throw new \RuntimeException('This is a test error from the web interface');
});

// Note: Catch-all 404 handler is defined in config/app.php after all routes are loaded
