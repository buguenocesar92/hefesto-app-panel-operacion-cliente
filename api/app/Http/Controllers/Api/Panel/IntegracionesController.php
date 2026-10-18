<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Panel;

use App\Http\Controllers\Controller;
use App\Services\Panel\ResumenService;
use Illuminate\Http\JsonResponse;

/**
 * Estado de cada integracion, con su motivo y su fuente de verificacion.
 *
 * Esta pantalla existe para que Hefesto vea que falta, y para que el cliente
 * entienda por que una tarjeta esta apagada. No es una pantalla oculta: un
 * panel con integraciones a medias es honesto y util.
 */
class IntegracionesController extends Controller
{
    public function __construct(private readonly ResumenService $resumen) {}

    public function __invoke(): JsonResponse
    {
        return response()->json([
            'datos' => $this->resumen->estadoDeIntegraciones(),
        ]);
    }
}