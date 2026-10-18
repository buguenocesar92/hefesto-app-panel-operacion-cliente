<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Panel;

use App\Http\Controllers\Controller;
use App\Services\Panel\RespaldosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Modulo 3: Respaldos.
 *
 * Solo lectura y solo contadores. No hay forma de pedir un respaldo ni de
 * descargar su contenido desde esta API: no existe el metodo HTTP parallo.
 */
class RespaldosController extends Controller
{
    public function __construct(private readonly RespaldosService $respaldos) {}

    public function __invoke(Request $peticion): JsonResponse
    {
        $limite = (int) $peticion->query('limite', (string) config('panel.respaldos_por_peticion'));

        return response()->json(['datos' => $this->respaldos->listar($limite)]);
    }
}