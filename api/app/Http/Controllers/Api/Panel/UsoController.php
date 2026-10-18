<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Panel;

use App\Http\Controllers\Controller;
use App\Services\Panel\UsoService;
use Illuminate\Http\JsonResponse;

/**
 * Modulo 5: Uso.
 *
 * `facturacion` viaja en null y siempre con su nota. El panel no emite
 * facturas: Hefesto ya tiene un sistema de facturacion y duplicarlo aqui
 * seria inventar un segundo libro de verdad.
 */
class UsoController extends Controller
{
    public function __construct(private readonly UsoService $uso) {}

    public function __invoke(): JsonResponse
    {
        return response()->json(['datos' => $this->uso->listar()]);
    }
}