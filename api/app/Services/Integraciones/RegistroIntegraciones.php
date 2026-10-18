<?php

declare(strict_types=1);

namespace App\Services\Integraciones;

use App\Enums\Sistema;
use App\Services\Integraciones\UptimeKuma\ClienteMetricasUptimeKuma;
use Illuminate\Contracts\Container\Container;

/**
 * Catalogo de integraciones.
 *
 * Aqui se decide, para cada sistema de Hefesto, si existe un cliente que
 * consulte o si la integracion queda desactivada y documentada.
 *
 * ESTADO DE CADA INTEGRACION EN ESTE PRIMER CORTE
 * ----------------------------------------------
 *  - Uptime Kuma: cliente implementado. Desactivado por defecto porque el
 *    mapeo entre las etiquetas de las metricas y los sitios del cliente hay
 *    que confirmarlo contra una instancia real.
 *  - SigNoz, Kopia, Coolify, CrowdSec, Authentik: SIN cliente. No se pudo
 *    verificar un endpoint de lectura con credencial de solo lectura. Sus
 *    motivos concretos estan en config/integraciones.php y en
 *    docs/02-INTEGRACIONES.md.
 *
 * Un sistema sin cliente NUNCA devuelve ceros: devuelve estado "desactivada"
 * con su motivo.
 */
final class RegistroIntegraciones
{
    /** @var array<string, Integracion> */
    private array $resueltas = [];

    public function __construct(private readonly Container $contenedor) {}

    /**
     * @return array<string, Integracion>
     */
    public function todas(): array
    {
        $resultado = [];

        foreach (Sistema::cases() as $sistema) {
            $resultado[$sistema->value] = $this->para($sistema);
        }

        return $resultado;
    }

    public function para(Sistema $sistema): Integracion
    {
        return $this->resueltas[$sistema->value] ??= $this->resolver($sistema);
    }

    private function resolver(Sistema $sistema): Integracion
    {
        $config = config('integraciones.'.$sistema->claveConfig());

        // Si el sistema no esta en la lista de activas de la instalacion, se
        // muestra como desactivado con su motivo, sin intentar resolverlo.
        $activas = (array) config('integraciones.activas');

        if (! in_array($sistema->value, $activas, true) || ! is_array($config)) {
            return new IntegracionDesactivada($sistema, $this->motivoPorDefecto($sistema));
        }

        if (! ($config['habilitada'] ?? false)) {
            return new IntegracionDesactivada($sistema, $this->motivoPorDefecto($sistema));
        }

        return match ($sistema) {
            Sistema::UptimeKuma => $this->contenedor->make(ClienteMetricasUptimeKuma::class),
            // Cualquier sistema futuro cae aqui hasta que exista un cliente
            // verificado. Es preferible a un `default` que consulte algo.
            default => new IntegracionDesactivada(
                $sistema,
                'Todavia no hay un cliente verificado de solo lectura para este sistema.'
            ),
        };
    }

    private function motivoPorDefecto(Sistema $sistema): string
    {
        $verificacion = config('integraciones.'.$sistema->claveConfig().'.verificacion');

        $pendiente = is_array($verificacion) ? ($verificacion['pendiente'] ?? null) : null;

        return $pendiente !== null && $pendiente !== ''
            ? (string) $pendiente
            : 'Integracion desactivada en esta instalacion.';
    }
}