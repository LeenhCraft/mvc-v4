<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HomeController extends BaseController
{
    public function index(Request $request, Response $response, array $args): Response
    {
        return $this->render($request, $response, 'home.twig', [
            'title' => 'Bienvenido a Windsurf MVC',
            'message' => '¡Hola! Has iniciado correctamente tu aplicación con Slim Framework 4 y Eloquent ORM.',
        ]);
    }
}
