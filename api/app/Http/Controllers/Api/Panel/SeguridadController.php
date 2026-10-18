<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Panel;

use App\Http\Controllers\Controller;
use App\Services\Panel\SeguridadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Modulo 4: Seguridad. Cantidades agregadas, sin IPs ni detalle.
 */
class SeguridadController extends Controller
{
    public function __construct(private readonly SeguridadService $seguridad) {}

    public function __invoke(Request $peticion): JsonResponse
    {
        $dias = (int) $peticion->query('dias', (string) config('panel.dias_seguridad_por_defecto'));

        return response()->json(['datos' => $this->seguridad->listar($dias)]);
    }
}