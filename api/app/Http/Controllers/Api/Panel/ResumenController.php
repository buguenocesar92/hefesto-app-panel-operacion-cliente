<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Panel;

use App\Http\Controllers\Controller;
use App\Services\Panel\ResumenService;
use Illuminate\Http\JsonResponse;

/**
 * Modulo 1: Resumen. Una tarjeta por sistema integrado.
 *
 * Este controlador CUENTA las tarjetas sin tocar los sistemas externos en el
 * peor caso posible: cada tarjeta se resuelve por separado y una que falla no
 * impide que las demas se pinten.
 */
class ResumenController extends Controller
{
    public function __construct(private readonly ResumenService $resumen) {}

    public function __invoke(): JsonResponse
    {
        return response()->json([
            'datos' => $this->resumen->tarjetas(),
            'politicas' => $this->resumen->politicas(),
        ]);
    }
}