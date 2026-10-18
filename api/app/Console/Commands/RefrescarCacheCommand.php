<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Cache\ConsultaCacheada;
use App\Services\Integraciones\RegistroIntegraciones;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Refresca la cache de consultas de todas las empresas.
 *
 * Es la unica tarea programada del panel. NO escribe en ningun sistema
 * externo: solo refresca lo que el panel ya tiene cacheado, para que un
 * cambio de TTL no dependa de que alguien recargue la pantalla.
 */
class RefrescarCacheCommand extends Command
{
    protected $signature = 'panel:refrescar-cache';

    protected $description = 'Refresca la cache de consultas del panel para todas las empresas activas.';

    public function handle(RegistroIntegraciones $registro, ConsultaCacheada $cache): int
    {
        $tenantIds = DB::table('tenants')->where('activo', true)->pluck('id');

        if ($tenantIds->isEmpty()) {
            $this->warn('No hay empresas activas.');

            return self::SUCCESS;
        }

        foreach ($tenantIds as $tenantId) {
            DB::transaction(function () use ($registro, $cache, $tenantId): void {
                DB::select('SELECT set_config(?, ?, true)', [ContextoTenant::VARIABLE, (string) $tenantId]);

                foreach ($registro->todas() as $integracion) {
                    $resultado = $cache->refrescarAhora($integracion, fn (): array => $integracion->consultar());

                    $this->line(sprintf(
                        '  %s: %s',
                        $integracion->sistema()->nombre(),
                        $resultado->estado->etiqueta()
                    ));
                }
            });
        }

        $this->info(sprintf('Cache refrescada para %d empresa(s).', $tenantIds->count()));

        return self::SUCCESS;
    }
}