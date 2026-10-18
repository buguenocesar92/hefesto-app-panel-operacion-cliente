<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\EstadoIntegracion;
use App\Enums\Sistema;
use App\Services\Cache\ConsultaCacheada;
use App\Services\Integraciones\Integracion;
use App\Services\Integraciones\IntegracionDesactivada;
use App\Services\Integraciones\ResultadoIntegracion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

/**
 * Pruebas de la cache de consultas: TTL, marcas de tiempo y degradacion.
 *
 * Ninguna de estas pruebas sale a la red: la consulta es un cierre que
 * devuelve datos o lanza una excepcion, como lo haria un sistema caido.
 */
class ConsultaCacheadaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::clear();
    }

    public function test_una_integracion_desactivada_no_consulta_nada(): void
    {
        $integracion = new IntegracionDesactivada(Sistema::Kopia, 'Integracion desactivada: falta credencial de solo lectura.');

        $llamadas = 0;

        $resultado = (new ConsultaCacheada(Cache::store('array')))->obtener(
            $integracion,
            function () use (&$llamadas): array {
                $llamadas++;

                return ['cualquier' => 'cosa'];
            }
        );

        $this->assertSame(EstadoIntegracion::Desactivada->value, $resultado->estado->value);
        $this->assertSame(0, $llamadas, 'Una integracion desactivada no debe consultar nada.');
    }

    public function test_la_consulta_exitosa_se_cachea_con_su_marca_de_tiempo(): void
    {
        config(['panel.ttl_por_sistema.uptime_kuma' => 120]);

        $integracion = $this->integracionFalsa(EstadoIntegracion::Disponible);

        $cache = new ConsultaCacheada(Cache::store('array'));

        $primera = $cache->obtener($integracion, fn (): array => ['series' => []]);

        $this->assertSame(EstadoIntegracion::Disponible->value, $primera->estado->value);
        $this->assertNotNull($primera->consultadoEn);
        $this->assertFalse($primera->desdeCache);

        $segunda = $cache->obtener($integracion, fn (): array => $this->fallar('no deberia volver a consultar'));

        $this->assertTrue($segunda->desdeCache, 'La segunda lectura debe venir de cache.');
        $this->assertEquals($primera->consultadoEn, $segunda->consultadoEn);
    }

    public function test_una_falla_no_tira_abajo_el_panel_y_no_se_cachea_largo(): void
    {
        $integracion = $this->integracionFalsa(EstadoIntegracion::Disponible);

        $cache = new ConsultaCacheada(Cache::store('array'));

        $resultado = $cache->obtener($integracion, function (): array {
            throw new RuntimeException('el sistema remoto cayo');
        });

        $this->assertSame(EstadoIntegracion::NoDisponible->value, $resultado->estado->value);

        // El mensaje que ve el cliente es generico: el detalle real queda en
        // el registro del servidor, no en la respuesta.
        $this->assertStringNotContainsString('el sistema remoto cayo', $resultado->mensaje);
        $this->assertSame([], $resultado->datos);
    }

    public function test_una_cache_vejez_se_marca_como_datos_antiguos(): void
    {
        config([
            'panel.umbral_datos_viejos.uptime_kuma' => 60,
            'panel.ttl_por_sistema.uptime_kuma' => 120,
        ]);

        $integracion = $this->integracionFalsa(EstadoIntegracion::Disponible);

        $cache = new ConsultaCacheada(Cache::store('array'));
        $clave = $cache->clave(Sistema::UptimeKuma);

        // Se simula una consulta hecha hace diez minutos.
        $cache->almacen()->put($clave, [
            'estado' => EstadoIntegracion::Disponible->value,
            'mensaje' => 'Consulta correcta.',
            'consultado_en' => CarbonImmutable::now()->subMinutes(10)->toIso8601String(),
            'datos' => ['series' => []],
        ], 3600);

        $resultado = $cache->obtener($integracion, fn (): array => $this->fallar('no deberia volver a consultar'));

        // El contenido sigue real, pero se dice que no esta en vivo.
        $this->assertSame(EstadoIntegracion::DatosViejos->value, $resultado->estado->value);
        $this->assertTrue($resultado->desdeCache);
    }

    private function integracionFalsa(EstadoIntegracion $estado): Integracion
    {
        return new class($estado) implements Integracion
        {
            public function __construct(private readonly EstadoIntegracion $estado) {}

            public function sistema(): Sistema
            {
                return Sistema::UptimeKuma;
            }

            public function estadoLocal(): ResultadoIntegracion
            {
                return new ResultadoIntegracion($this->sistema(), $this->estado, 'Listo.');
            }

            public function consultar(): array
            {
                return [];
            }
        };
    }

    private function fallar(string $mensaje): array
    {
        throw new RuntimeException($mensaje);
    }
}