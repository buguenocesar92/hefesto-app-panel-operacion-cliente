<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Tenancy\ContextoTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija el tenant de la peticion y rechaza lo que no pertenezca al usuario.
 *
 * ESTA ES LA CLASE QUE SOSTIENE EL AISLAMIENTO ENTRE PYMES.
 *
 * Lee el tenant desde el usuario autenticado. Jamás desde un parametro de la
 * ruta, desde el cuerpo de la peticion ni desde una cabecera: si el tenant
 * se aceptara como entrada, cambiar un id en la URL bastaria para leer los
 * datos de otra empresa.
 *
 * Ademas se niega a servir cualquier ruta que no sea de lectura, aunque
 * public/index.php ya lo bloquee. Es una segunda barrera deliberada: si
 * alguien sirviera el panel por otro camino (un route de Laravel distinto,
 * un comando que atienda HTTP), la regla sigue en pie.
 */
class ResolverTenant
{
    public function handle(Request $peticion, Closure $siguiente): Response
    {
        if (! $peticion->isMethodSafe()) {
            return response()->json([
                'error' => [
                    'codigo' => 405,
                    'mensaje' => 'El Panel de Operacion es de solo lectura.',
                ],
            ], 405);
        }

        $usuario = $peticion->user();

        if ($usuario !== null && $usuario->tenant_id === null) {
            return response()->json([
                'error' => [
                    'codigo' => 403,
                    'mensaje' => 'La cuenta no esta asociada a ninguna empresa.',
                ],
            ], 403);
        }

        // ContextoTenant::actual() ya lee del usuario; no hay setter publico
        // que acepte un tenant desde afuera a proposito.
        if ($usuario !== null && ContextoTenant::actual() === null) {
            return response()->json([
                'error' => [
                    'codigo' => 403,
                    'mensaje' => 'No se pudo determinar la empresa de la sesion.',
                ],
            ], 403);
        }

        return $siguiente($peticion);
    }
}