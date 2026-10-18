<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * Controlador base del panel.
 *
 * No trae autorizacion por politica ni validacion de formularios: el panel es
 * de solo lectura y su unica regla de acceso es "estas autenticado y tu
 * usuario pertenece a una empresa". Esa regla vive en AutenticarConToken y
 * ResolverTenant, no aqui.
 */
class Controller extends BaseController
{
    use AuthorizesRequests;
}