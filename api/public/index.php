<?php

declare(strict_types=1);

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Solo lectura: este panel nunca acepta peticiones que modifiquen datos.
// Las peticiones que no sean GET sobre la API se rechazan aqui, antes de
// llegar a cualquier controlador. Ver docs/07-SEGURIDAD-Y-AISLAMIENTO.md.
if (PHP_SAPI !== 'cli' && ! in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD', 'OPTIONS'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD, OPTIONS');
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'error' => [
            'codigo' => 405,
            'mensaje' => 'El Panel de Operacion es de solo lectura: no acepta operaciones que modifiquen datos.',
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    exit;
}

if (file_exists($mantenimiento = __DIR__.'/../storage/framework/maintenance.php')) {
    require $mantenimiento;
}

require __DIR__.'/../vendor/autoload.php';

/** @var Illuminate\Foundation\Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());