<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Panel;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Services\Panel\SitiosService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Modulo 2: Sitios.
 *
 * El `{sitio}` se busca con el alcance global de tenant: pedir el id de un
 * sitio de otra PyME devuelve 404, no 403 y no el objeto. Un 403 confirmaria
 * que ese id existe, que ya es filtrar informacion de otro cliente.
 */
class SitiosController extends Controller
{
    public function __construct(private readonly SitiosService $sitios) {}

    public function index(): JsonResponse
    {
        return response()->json(['datos' => $this->sitios->listar()]);
    }

    public function show(string $sitio): JsonResponse
    {
        $modelo = Site::query()->find($sitio);

        if ($modelo === null) {
            throw new NotFoundHttpException('El sitio no existe.');
        }

        $detalle = $this->sitios->uno($modelo);

        if ($detalle === null) {
            throw new NotFoundHttpException('El sitio no existe.');
        }

        return response()->json(['datos' => $detalle]);
    }
}