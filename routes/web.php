<?php

declare(strict_types=1);

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

$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function (Request $request, Response $response): Response {
    $response->getBody()->write('404 - Página no encontrada');
    return $response->withStatus(404);
});
