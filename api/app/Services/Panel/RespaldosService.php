<?php

declare(strict_types=1);

namespace App\Services\Panel;

use App\Models\BackupRun;
use App\Services\Tenancy\ContextoTenant;
use Carbon\CarbonImmutable;

/**
 * Modulo 3: Respaldos. Historial y verificaciones.
 *
 * Lo que se entrega son contadores, nunca contenido. No hay forma de pedir un
 * archivo por esta API.
 *
 * Cuando no hay registros, la respuesta dice `sin_registros: true` y el
 * ultimo_respaldo queda en null. No devuelve un objeto vacio con estado
 * "exitoso": un respaldo que nadie ha visto no es un respaldo que haya dado
 * bien.
 */
final class RespaldosService
{
    /**
     * @return array<string, mixed>
     */
    public function listar(int $limite = 30): array
    {
        $limite = max(1, min($limite, 100));

        $registros = BackupRun::query()
            ->orderByDesc('iniciado_en')
            ->limit($limite)
            ->get();

        $ultimo = $registros->first();

        $ultimoExitoso = BackupRun::query()
            ->where('estado', 'exitoso')
            ->orderByDesc('iniciado_en')
            ->first();

        return [
            'generado_en' => CarbonImmutable::now()->toIso8601String(),
            'tenant_activo' => ContextoTenant::actual(),
            'sin_registros' => $registros->isEmpty(),
            'ultimo_respaldo' => $ultimo?->resumen(),
            'ultimo_respaldo_exitoso' => $ultimoExitoso?->resumen(),
            'respaldos' => $registros->map(fn (BackupRun $r): array => $r->resumen())->values()->all(),
            'lectura_desde_cache' => true,
            'ttl_cache_segundos' => (int) config('panel.ttl_por_sistema.kopia'),
        ];
    }

    /**
     * Resumen para la tarjeta del modulo Resumen.
     *
     * @return array<string, mixed>
     */
    public function resumenParaTarjeta(): array
    {
        $ultimo = BackupRun::query()->orderByDesc('iniciado_en')->first();

        if ($ultimo === null) {
            return [
                'hay_registros' => false,
                'texto' => 'Sin registros de respaldo',
            ];
        }

        return [
            'hay_registros' => true,
            'ultimo_en' => $ultimo->iniciado_en?->toIso8601String(),
            'estado' => $ultimo->estado->value,
            'estado_texto' => $ultimo->estado->etiqueta(),
            'verificacion' => $ultimo->verificacion->value,
            'verificacion_texto' => $ultimo->verificacion->etiqueta(),
            'archivos' => $ultimo->archivos,
            'texto' => sprintf(
                'Ultimo respaldo: %s, %s, %s archivos',
                $ultimo->estado->etiqueta(),
                $ultimo->verificacion->etiqueta(),
                number_format((int) $ultimo->archivos, 0, ',', '.')
            ),
        ];
    }
}