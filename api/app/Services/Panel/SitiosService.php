<?php

declare(strict_types=1);

namespace App\Services\Panel;

use App\Enums\Sistema;
use App\Models\Site;
use App\Services\Cache\ConsultaCacheada;
use App\Services\Integraciones\RegistroIntegraciones;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Modulo 2: Sitios. Disponibilidad del sitio del cliente.
 *
 * DECISION DE DISENO IMPORTANTE: este servicio NO inventa el significado de
 * las metricas. Toma las series que devuelve Uptime Kuma y busca, entre sus
 * etiquetas, alguna que contenga el host del sitio registrado por Hefesto.
 *
 * Por que asi y no con nombres de metricas fijos: los nombres y etiquetas que
 * publica Uptime Kuma no estan documentados oficialmente. Si escribieramos
 * "monitor_status = 1 significa arriba", estariamos afirmando algo que no
 * verificamos, y un cliente veria "disponible" sobre la base de un dato
 * inventado.
 *
 * Si no hay coincidencia, el sitio queda en "sin_datos": que es distinto de
 * "caido" y distinto de "ok".
 */
final class SitiosService
{
    public function __construct(
        private readonly RegistroIntegraciones $registro,
        private readonly ConsultaCacheada $cache,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function listar(): array
    {
        $integracion = $this->registro->para(Sistema::UptimeKuma);
        $resultado = $this->cache->obtener($integracion, fn (): array => $integracion->consultar());

        $sitios = Site::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        return [
            'generado_en' => CarbonImmutable::now()->toIso8601String(),
            'origen' => [
                'sistema' => $resultado->sistema->value,
                'estado' => $resultado->estado->value,
                'actualizado_en' => $resultado->consultadoEn?->toIso8601String(),
            ],
            'sitios' => $sitios
                ->map(fn (Site $sitio): array => $this->presentar($sitio, $resultado->datos['series'] ?? []))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function uno(Site $sitio): ?array
    {
        $integracion = $this->registro->para(Sistema::UptimeKuma);
        $resultado = $this->cache->obtener($integracion, fn (): array => $integracion->consultar());

        return $this->presentar($sitio, $resultado->datos['series'] ?? []);
    }

    /**
     * Cruza el host del sitio con las etiquetas de las series.
     *
     * @param  list<array{nombre: string, etiquetas: array<string, string>, valor: float}>  $series
     * @return array<string, mixed>
     */
    private function presentar(Site $sitio, array $series): array
    {
        $coincidencias = $this->seriesDelHost($sitio->host, $series);

        if ($coincidencias === []) {
            return [
                'id' => $sitio->id,
                'nombre' => $sitio->nombre,
                'host' => $sitio->host,
                'disponibilidad' => 'sin_datos',
                'disponibilidad_texto' => 'Sin informacion disponible',
                'series_relacionadas' => 0,
                'nota' => 'El sistema de monitoreo no entrego datos que coincidan con este sitio.',
            ];
        }

        return [
            'id' => $sitio->id,
            'nombre' => $sitio->nombre,
            'host' => $sitio->host,
            'disponibilidad' => 'informado',
            'disponibilidad_texto' => 'El monitoreo entrego datos',
            'series_relacionadas' => count($coincidencias),
            'muestra' => array_slice(array_map(
                fn (array $serie): array => [
                    'metrica' => $serie['nombre'],
                    'valor' => $serie['valor'],
                ],
                $coincidencias
            ), 0, 5),
            'nota' => 'Los valores se muestran tal como los publica el sistema de monitoreo.',
        ];
    }

    /**
     * @param  list<array{nombre: string, etiquetas: array<string, string>, valor: float}>  $series
     * @return list<array{nombre: string, etiquetas: array<string, string>, valor: float}>
     */
    private function seriesDelHost(string $host, array $series): array
    {
        $host = mb_strtolower($host);

        return array_values(array_filter(
            $series,
            function (array $serie) use ($host): bool {
                foreach ($serie['etiquetas'] as $valor) {
                    if (mb_strtolower($valor) === $host) {
                        return true;
                    }
                }

                return false;
            }
        ));
    }

    /** @param  Collection<int, Site>  $sitios */
    public function total(Collection $sitios): int
    {
        return $sitios->count();
    }
}