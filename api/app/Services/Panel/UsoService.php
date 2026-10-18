<?php

declare(strict_types=1);

namespace App\Services\Panel;

use App\Enums\Sistema;
use App\Services\Cache\ConsultaCacheada;
use App\Services\Integraciones\RegistroIntegraciones;
use Carbon\CarbonImmutable;

/**
 * Modulo 5: Uso.
 *
 * AQUI NO HAY FACTURACION, y ladecision esta documentada en
 * docs/05-CONFIGURACION.md: Hefesto ya tiene un sistema de facturacion y este
 * panel no debe inventar un segundo. Lo que este modulo muestra es USO, con
 * los mismos numeros que puede entregar un sistema de monitoreo.
 *
 * Cada recurso se declara con su sistema de origen. Si el sistema no respondio
 * o esta desactivado, el recurso aparece con `estado: sin_datos` y valor null.
 * Un cero sin origen seria un dato falso.
 */
final class UsoService
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
        $recursos = [];

        foreach (Sistema::cases() as $sistema) {
            $resultado = $this->cache->obtener(
                $this->registro->para($sistema),
                fn (): array => $this->registro->para($sistema)->consultar(),
            );

            $recursos[] = [
                'recurso' => $sistema->nombre(),
                'sistema_origen' => $sistema->value,
                'estado_origen' => $resultado->estado->value,
                'hay_datos' => $resultado->estado->value === 'disponible',
                'actualizado_en' => $resultado->consultadoEn?->toIso8601String(),
            ];
        }

        return [
            'generado_en' => CarbonImmutable::now()->toIso8601String(),
            'recursos' => $recursos,
            'facturacion' => null,
            'nota_facturacion' => 'Este panel no emite facturas ni cobros. '
                .'Las cifras de facturacion viven en el sistema de Hefesto.',
        ];
    }
}