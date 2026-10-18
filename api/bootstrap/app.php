<?php

declare(strict_types=1);

use App\Http\Middleware\AutenticarConToken;
use App\Http\Middleware\ResolverTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Autenticacion propia por token de acceso (hash SHA-256 en la base).
        // No se usa Sanctum a proposito: el esquema de la tabla de tokens es
        // codigo de Hefesto, para no depender de migraciones de terceros.
        $middleware->alias([
            'token' => AutenticarConToken::class,
        ]);

        // El tenant se resuelve SIEMPRE desde el usuario autenticado. Nunca
        // desde un parametro de la ruta ni desde el cuerpo: eso permitiria
        // que alguien pidiera los datos de otra PyME cambiando el id.
        $middleware->append(ResolverTenant::class);

        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // La API nunca devuelve trazas ni detalle interno al cliente.
        $exceptions->render(function (Throwable $e, Request $peticion) {
            if (! $peticion->is('api/*')) {
                return null;
            }

            if ($e instanceof Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                return response()->json([
                    'error' => [
                        'codigo' => $e->getStatusCode(),
                        'mensaje' => $e->getMessage() !== ''
                            ? $e->getMessage()
                            : 'No se pudo completar la solicitud.',
                    ],
                ], $e->getStatusCode());
            }

            return response()->json([
                'error' => [
                    'codigo' => 500,
                    'mensaje' => 'Ocurrio un error interno. El equipo de Hefesto fue notificado.',
                ],
            ], 500);
        });
    })
    ->create();