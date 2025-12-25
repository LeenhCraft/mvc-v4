<?php

use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Illuminate\Database\Capsule\Manager as Capsule;

// Cargar variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Configurar la aplicación
$app = AppFactory::create();

// Configurar el manejador de errores
$errorMiddleware = $app->addErrorMiddleware(
    $_ENV['APP_DEBUG'] === 'true',
    true,
    true
);

// Configurar la base de datos con Eloquent
$capsule = new Capsule;
$capsule->addConnection([
    'driver'    => $_ENV['DB_CONNECTION'],
    'host'      => $_ENV['DB_HOST'],
    'database'  => $_ENV['DB_DATABASE'],
    'username'  => $_ENV['DB_USERNAME'],
    'password'  => $_ENV['DB_PASSWORD'],
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => '',
]);

// Hacer que Eloquent esté disponible globalmente
$capsule->setAsGlobal();
$capsule->bootEloquent();

// Configurar Twig
$twig = Twig::create(__DIR__ . '/../resources/views', [
    'cache' => $_ENV['APP_ENV'] === 'production' ? __DIR__ . '/../storage/cache/views' : false,
    'debug' => $_ENV['APP_DEBUG'] === 'true',
    'auto_reload' => true,
]);

// Añadir extensión de depuración si estamos en desarrollo
if ($_ENV['APP_DEBUG'] === 'true') {
    $twig->addExtension(new \Twig\Extension\DebugExtension());
}

// Añadir Twig a la aplicación
$app->add(TwigMiddleware::create($app, $twig));

// Añadir el middleware de enrutamiento
$app->addRoutingMiddleware();

// Añadir el middleware de parseo de body
$app->addBodyParsingMiddleware();

// Configuración de CORS (si es necesario)
$app->add(function ($request, $handler) {
    if (strtoupper($request->getMethod()) === 'OPTIONS') {
        $response = new \Slim\Psr7\Response();
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
    }
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

// Incluir rutas
require __DIR__ . '/../routes/web.php';

return $app;
