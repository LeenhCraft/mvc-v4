<?php

use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Illuminate\Database\Capsule\Manager as Capsule;
use App\Middleware\CentinelaMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Services\Session\SessionManager;
use App\Services\Csrf\CsrfProtection;
use App\Services\Csrf\CsrfTwigExtension;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Cargar variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Configurar la aplicación
$app = AppFactory::create();

// Configurar el manejador de errores
$displayErrorDetails = $_ENV['APP_DEBUG'] === 'true';
$logErrors = true;
$logErrorDetails = true;

$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, $logErrors, $logErrorDetails);

// Custom error handler using Slim's error handler
use Slim\Handlers\ErrorHandler;
use Psr\Http\Message\ResponseInterface;

$customErrorHandler = function (
    \Psr\Http\Message\ServerRequestInterface $request,
    \Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails
) use ($app): ResponseInterface {

    $payload = ['error' => 'Internal Server Error'];

    // Check if it's an API request
    $path = $request->getUri()->getPath();
    $acceptHeader = $request->getHeaderLine('Accept');
    $isApiRequest = str_starts_with($path, '/api/') || str_contains($acceptHeader, 'application/json');

    if ($isApiRequest) {
        // Return JSON for API requests
        $response = $app->getResponseFactory()->createResponse(500);

        $error = [
            'error' => 'Internal Server Error',
            'message' => $displayErrorDetails ? $exception->getMessage() : 'An unexpected error occurred',
        ];

        if ($displayErrorDetails) {
            $error['file'] = $exception->getFile();
            $error['line'] = $exception->getLine();
            $error['trace'] = array_slice(explode("\n", $exception->getTraceAsString()), 0, 10);
        }

        $jsonPayload = json_encode($error, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $response->getBody()->write($jsonPayload);

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    // Return HTML for web requests
    $response = $app->getResponseFactory()->createResponse(500);

    try {
        $view = Twig::fromRequest($request);
        return $view->render($response, 'errors/500.twig', [
            'title' => '500 - Error Interno del Servidor',
            'error_message' => $displayErrorDetails ? $exception->getMessage() : null,
            'error_file' => $displayErrorDetails ? $exception->getFile() : null,
            'error_line' => $displayErrorDetails ? $exception->getLine() : null,
            'show_details' => $displayErrorDetails,
            'request_id' => uniqid('err_', true),
        ])->withStatus(500);
    } catch (\Exception $e) {
        // Fallback if Twig fails
        $response->getBody()->write('500 - Internal Server Error');
        if ($displayErrorDetails) {
            $response->getBody()->write('<pre>' . htmlspecialchars($exception->getMessage()) . '</pre>');
        }
        return $response;
    }
};

$errorMiddleware->setDefaultErrorHandler($customErrorHandler);

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

// Configurar CSRF Protection y añadir extensión a Twig
$session = new SessionManager();
$csrf = new CsrfProtection($session);
$twig->addExtension(new CsrfTwigExtension($csrf));

// Añadir Twig a la aplicación
$app->add(TwigMiddleware::create($app, $twig));

// Añadir el middleware de parseo de body
$app->addBodyParsingMiddleware();

// Centinela: función helper para parsear configuración (evita duplicación)
$getCentinelaConfig = function (): array {
    $enabled = filter_var($_ENV['CENTINELA_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
    $maxBodyBytes = (int)($_ENV['CENTINELA_MAX_BODY_BYTES'] ?? 16384);

    $dirEnv = (string)($_ENV['CENTINELA_DIR'] ?? '');
    $dir = trim($dirEnv) !== '' ? $dirEnv : (dirname(__DIR__) . DIRECTORY_SEPARATOR . 'centinela');

    $redactHeaders = array_filter(array_map('trim', explode(',', (string)($_ENV['CENTINELA_REDACT_HEADERS'] ?? 'authorization,cookie,set-cookie'))));
    $redactResponseHeaders = array_filter(array_map('trim', explode(',', (string)($_ENV['CENTINELA_REDACT_RESPONSE_HEADERS'] ?? 'set-cookie'))));

    $outputRaw = strtolower(trim((string)($_ENV['CENTINELA_OUTPUT'] ?? 'file')));
    $outputTargets = preg_split('/[\s,]+/', $outputRaw, -1, PREG_SPLIT_NO_EMPTY) ?: [];

    if (in_array($outputRaw, ['both', 'all'], true)) {
        $outputTargets = ['file', 'db'];
    }

    $fileEnabled = in_array('file', $outputTargets, true) || in_array('json', $outputTargets, true);
    $dbOutputEnabled = in_array('db', $outputTargets, true);

    if ($outputRaw === '' || $outputTargets === []) {
        $fileEnabled = true;
        $dbOutputEnabled = false;
    }

    $dbEnabled = filter_var($_ENV['CENTINELA_DB_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN) && $dbOutputEnabled;
    $dbTableEnv = (string)($_ENV['CENTINELA_DB_TABLE'] ?? 'centinela_logs');
    $dbTable = trim($dbTableEnv) !== '' ? $dbTableEnv : 'centinela_logs';
    $dbAutoMigrate = filter_var($_ENV['CENTINELA_DB_AUTO_MIGRATE'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

    return [
        'enabled' => $enabled,
        'file_enabled' => $fileEnabled,
        'dir' => $dir,
        'max_body_bytes' => $maxBodyBytes,
        'redact_headers' => $redactHeaders,
        'redact_response_headers' => $redactResponseHeaders,
        'db' => [
            'enabled' => $dbEnabled,
            'table' => $dbTable,
            'auto_migrate' => $dbAutoMigrate,
        ],
    ];
};

// Parsear configuración de Centinela una sola vez
$centinelaConfig = $getCentinelaConfig();

// Añadir middleware de Centinela
$app->add(new CentinelaMiddleware($centinelaConfig));

// Configuración de CSRF: parsear desde .env
$getCsrfConfig = function (): array {
    $enabled = filter_var($_ENV['CSRF_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
    $excludedPaths = array_filter(array_map('trim', explode(',', (string)($_ENV['CSRF_EXCLUDED_PATHS'] ?? ''))));

    return [
        'enabled' => $enabled,
        'excluded_paths' => $excludedPaths,
    ];
};

$csrfConfig = $getCsrfConfig();

// Añadir middleware de CSRF
$app->add(new CsrfMiddleware($csrfConfig));

// Añadir el middleware de enrutamiento
$app->addRoutingMiddleware();

// Configuración de CORS: parsear desde .env
$getCorsConfig = function (): array {
    $allowedOrigins = (string)($_ENV['CORS_ALLOWED_ORIGINS'] ?? '*');
    $allowedMethods = (string)($_ENV['CORS_ALLOWED_METHODS'] ?? 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
    $allowedHeaders = (string)($_ENV['CORS_ALLOWED_HEADERS'] ?? 'X-Requested-With, Content-Type, Accept, Origin, Authorization');
    $maxAge = (int)($_ENV['CORS_MAX_AGE'] ?? 86400);
    $allowCredentials = filter_var($_ENV['CORS_ALLOW_CREDENTIALS'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

    return [
        'allowed_origins' => $allowedOrigins,
        'allowed_methods' => $allowedMethods,
        'allowed_headers' => $allowedHeaders,
        'max_age' => $maxAge,
        'allow_credentials' => $allowCredentials,
    ];
};

$corsConfig = $getCorsConfig();

// Añadir middleware de CORS
$app->add(new CorsMiddleware($corsConfig));

// Incluir rutas
require __DIR__ . '/../routes/web.php';
require __DIR__ . '/../routes/api.php';

// Catch-all 404 handler (must be last)
$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function (Request $request, Response $response) use ($app): Response {
    $path = $request->getUri()->getPath();
    $method = $request->getMethod();

    // Check if request expects JSON (API request or Accept header)
    $acceptHeader = $request->getHeaderLine('Accept');
    $isApiRequest = str_starts_with($path, '/api/') ||
                    str_contains($acceptHeader, 'application/json');

    if ($isApiRequest) {
        // Return JSON error for API routes
        $payload = json_encode([
            'error' => 'Not Found',
            'message' => 'The requested API endpoint does not exist',
            'path' => $path,
            'method' => $method,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus(404);
    }

    // Return HTML error page for web routes
    try {
        $view = Twig::fromRequest($request);
        return $view->render($response, 'errors/404.twig', [
            'title' => '404 - Página no encontrada',
            'request_path' => $path,
            'request_method' => $method,
        ])->withStatus(404);
    } catch (\Exception $e) {
        // Fallback if Twig template fails
        $response->getBody()->write('404 - Página no encontrada');
        return $response->withStatus(404);
    }
});

return $app;
