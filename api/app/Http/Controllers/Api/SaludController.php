<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

/**
 * Comprobacion de vida del panel. Publica y sin datos.
 *
 * Devuelve lo minimo: que la app responde y en que momento. Si devolviera el
 * estado de los sistemas, un tercero sin cuenta podria mapear la
 * infraestructura de Hefesto solo con golpear una URL.
 */
class SaludController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'estado' => 'ok',
            'aplicacion' => config('app.name'),
            'hora' => CarbonImmutable::now()->toIso8601String(),
            'modo' => config('app.env'),
            'solo_lectura' => true,
        ]);
    }
}