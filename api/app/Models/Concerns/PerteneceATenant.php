<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Alcance de tenant aplicado a todos los modelos que tienen tenant_id.
 *
 * Por que NO alcanza con filtrar en los controladores: un endpoint nuevo, o
 * una consulta hecha desde un comando o una vista, se olvidaria del filtro.
 * Con el alcance global,olvidarse no entrega datos de otro cliente: entrega
 * menos datos.
 *
 * La clase usa dos barreras complementarias:
 *
 *  1. Este alcance de Eloquent, que agrega `where tenant_id = ?`.
 *  2. Las politicas RLS de PostgreSQL (migracion ..._000008), que son la
 *     ultima linea y siguen valiendo aunque alguien escriba SQL a mano.
 *
 * Ver docs/07-SEGURIDAD-Y-AISLAMIENTO.md.
 */
trait PerteneceATenant
{
    public static function bootPerteneceATenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $consulta): void {
            $tenantId = ContextoTenant::actual();

            if ($tenantId === null) {
                // Sin tenant resuelto, NO se devuelven filas. La alternativa
                // seria devolver todas, que es exactamente la fuga que este
                // panel no puede permitirse.
                throw new RuntimeException(sprintf(
                    'Se consulto %s sin un tenant resuelto. Llama a ContextoTenant::ejecutarAislado() primero.',
                    static::class
                ));
            }

            $consulta->where($consulta->getModel()->getTable().'.tenant_id', $tenantId);
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}