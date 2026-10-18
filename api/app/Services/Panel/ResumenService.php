<?php

declare(strict_types=1);

namespace App\Services\Panel;

use App\Enums\EstadoIntegracion;
use App\Enums\Sistema;
use App\Services\Cache\ConsultaCacheada;
use App\Services\Integraciones\RegistroIntegraciones;
use App\Services\Integraciones\ResultadoIntegracion;
use Carbon\CarbonImmutable;

/**
 * Modulo 1: Resumen. Una tarjeta por sistema integrado.
 *
 * Este servicio es el corazon de la degradacion elegante: consulta TODOS los
 * sistemas de forma independiente y arma la respuesta con lo que hay. Si uno
 * falla, la tarjeta de ese sistema dice "no disponible" y las demas se pintan
 * igual. Nunca hay un estado agregado tipo "todo bien" que tape una falla.
 */
final class ResumenService
{
    public function __construct(
        private readonly RegistroIntegraciones $registro,
        private readonly ConsultaCacheada $cache,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function tarjetas(): array
    {
        $tarjetas = [];

        foreach (Sistema::cases() as $sistema) {
            $tarjetas[] = $this->tarjeta($sistema);
        }

        return [
            'generado_en' => CarbonImmutable::now()->toIso8601String(),
            'tarjetas' => $tarjetas,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function tarjeta(Sistema $sistema): array
    {
        $integracion = $this->registro->para($sistema);

        $resultado = $this->cache->obtener($integracion, fn (): array => $integracion->consultar());

        $config = config('integraciones.'.$sistema->claveConfig().'.verificacion');
        $verificacion = is_array($config) ? ($config['estado'] ?? 'no_verificada') : 'no_verificada';

        return [
            'sistema' => $sistema->value,
            'nombre' => $sistema->nombre(),
            'que_aporta' => $sistema->resumen(),
            'estado' => $resultado->estado->value,
            'estado_texto' => $resultado->estado->etiqueta(),
            'es_degradado' => $resultado->estado->esDegradado(),
            'requiere_atencion' => $resultado->estado->esFalla(),
            'mensaje' => $resultado->mensaje,
            // La hora de la ultima actualizacion viaja SIEMPRE: el cliente
            // tiene que poder ver que lo que mira no es en vivo.
            'actualizado_en' => $resultado->consultadoEn?->toIso8601String(),
            'desde_cache' => $resultado->desdeCache,
            'verificacion_integracion' => $verificacion,
            'escritura_posible' => false,
        ];
    }

    /**
     * Estado de las integraciones por separado: util para la pantalla
     * "integraciones" y para que Hefesto sepa que tiene pendiente.
     *
     * @return array<string, mixed>
     */
    public function estadoDeIntegraciones(): array
    {
        $items = [];

        foreach ($this->tarjetas()['tarjetas'] as $tarjeta) {
            $sistema = Sistema::from($tarjeta['sistema']);
            $config = config('integraciones.'.$sistema->claveConfig());

            $items[] = [
                ...$tarjeta,
                'habilitada_en_config' => (bool) (is_array($config) ? ($config['habilitada'] ?? false) : false),
                'fuente_verificacion' => is_array($config) ? ($config['verificacion']['fuente'] ?? null) : null,
                'pendiente' => is_array($config) ? ($config['verificacion']['pendiente'] ?? null) : null,
            ];
        }

        return [
            'generado_en' => CarbonImmutable::now()->toIso8601String(),
            'integraciones' => $items,
            'resumen_de_estados' => $this->contarEstados($items),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, int>
     */
    private function contarEstados(array $items): array
    {
        $conteo = [];

        foreach ($items as $item) {
            $estado = (string) $item['estado'];
            $conteo[$estado] = ($conteo[$estado] ?? 0) + 1;
        }

        return $conteo;
    }

    /** El panel es de solo lectura; se declara explicitamente en la API. */
    public function politicas(): array
    {
        return [
            'solo_lectura' => true,
            'puede_iniciar_respaldo' => false,
            'puede_reiniciar_servicio' => false,
            'puede_ver_contenido_de_respaldos' => false,
            'nota' => 'Este panel no ejecuta acciones sobre los sistemas de Hefesto. '
                .'Para solicitar un respaldo o un reinicio, contacte a Hefesto.',
        ];
    }

    public function estadoDe(Sistema $sistema): EstadoIntegracion
    {
        return $this->cache
            ->obtener($this->registro->para($sistema), fn (): array => $this->registro->para($sistema)->consultar())
            ->estado;
    }

    public function resultadoDe(Sistema $sistema): ResultadoIntegracion
    {
        $integracion = $this->registro->para($sistema);

        return $this->cache->obtener($integracion, fn (): array => $integracion->consultar());
    }
}