<?php

declare(strict_types=1);

namespace App\Services\Panel;

use App\Models\SecurityCounter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Modulo 4: Seguridad. Ataques detectados y bloqueos aplicados.
 *
 * Se muestran CANTIDADES, nunca quien los hizo. No hay direccion IP, ni
 * agente de usuario, ni ruta atacada, ni carga util: una IP identifica a una
 * persona y el panel no es el lugar para exponerla.
 */
final class SeguridadService
{
    /**
     * @return array<string, mixed>
     */
    public function listar(int $dias = 7): array
    {
        $dias = max(1, min($dias, (int) config('panel.dias_seguridad_maximo')));

        $desde = CarbonImmutable::now()->subDays($dias - 1)->startOfDay();

        $registros = SecurityCounter::query()
            ->where('dia', '>=', $desde->toDateString())
            ->orderBy('dia')
            ->get();

        return [
            'generado_en' => CarbonImmutable::now()->toIso8601String(),
            'dias' => $dias,
            'sin_registros' => $registros->isEmpty(),
            'totales' => [
                'detectados' => (int) $registros->sum('detectados'),
                'bloqueados' => (int) $registros->sum('bloqueados'),
            ],
            'por_dia' => $registros->map(fn (SecurityCounter $c): array => $c->resumen())->values()->all(),
            'por_tipo' => $this->acumularTipos($registros),
            'nota' => 'Solo cantidades agregadas. El panel no muestra IPs ni detalle de ataques.',
        ];
    }

    /**
     * @param  Collection<int, SecurityCounter>  $registros
     * @return array<string, int>
     */
    private function acumularTipos(Collection $registros): array
    {
        $tipos = [];

        foreach ($registros as $registro) {
            foreach ((array) ($registro->por_tipo ?? []) as $tipo => $cantidad) {
                $tipos[$tipo] = ($tipos[$tipo] ?? 0) + (int) $cantidad;
            }
        }

        arsort($tipos);

        return $tipos;
    }
}