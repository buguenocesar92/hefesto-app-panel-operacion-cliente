<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use Closure;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Contexto de tenant de la peticion.
 *
 * La seguridad de fila (RLS) de PostgreSQL lee el tenant desde la variable
 * de sesion `app.tenant_id`. Esta clase es la UNICA que la escribe, y solo
 * dentro de una transaccion.
 *
 * ALCANCE DE TRANSACCION
 * ----------------------
 * Se usa `set_config(..., true)`: el valor desaparece al confirmar o revertir
 * la transaccion. Con un SET normal (ambito de sesion) el valor sobrevive, y
 * en un worker que reutiliza la conexion para peticiones de Pymes distintas,
 * una peticion podria leer datos de otra PyME. Ese es exactamente el fallo
 * que este diseno evita.
 */
class ContextoTenant
{
    /** Variable de sesion de PostgreSQL que leen las politicas RLS. */
    public const VARIABLE = 'app.tenant_id';

    /**
     * Fija el tenant en la conexion actual.
     *
     * @throws RuntimeException si no hay una transaccion abierta.
     */
    public function establecer(string $tenantId): void
    {
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException(
                'No se puede fijar el contexto de tenant fuera de una transaccion. '
                .'Use ContextoTenant::ejecutarAislado().'
            );
        }

        DB::select('SELECT set_config(?, ?, true)', [self::VARIABLE, $tenantId]);
    }

    /**
     * Ejecuta una operacion aislada al tenant indicado.
     *
     * @template T
     *
     * @param  Closure(): T  $operacion
     * @return T
     */
    public function ejecutarAislado(string $tenantId, Closure $operacion): mixed
    {
        return DB::transaction(function () use ($tenantId, $operacion) {
            $this->establecer($tenantId);

            return $operacion();
        });
    }

    /**
     * Tenant resuelto de la peticion, o null si todavia no hay.
     *
     * Lo escribe unicamente el middleware ResolverTenant, y lo hace leyendo
     * el usuario autenticado. Nunca desde un parametro.
     */
    public static function actual(): ?string
    {
        $usuario = auth()->user();

        $tenantId = $usuario?->tenant_id;

        return $tenantId === null ? null : (string) $tenantId;
    }

    /** Identificador de la peticion para diagnostico. */
    public static function enConexion(): ?string
    {
        $fila = DB::selectOne('SELECT current_setting(?, true) AS valor', [self::VARIABLE]);

        $valor = $fila->valor ?? null;

        return $valor === null || $valor === '' ? null : (string) $valor;
    }
}