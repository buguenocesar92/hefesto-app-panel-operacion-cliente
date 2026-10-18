<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\EstadoIntegracion;
use App\Enums\Sistema;
use App\Services\Integraciones\RegistroIntegraciones;
use Tests\TestCase;

/**
 * El catalogo de integraciones es la parte del panel que mas evoluciona: cada
 * vez que Hefesto verifique un sistema nuevo, hay que sumar su cliente. Estas
 * pruebas protegen la regla que hace seguro ese trabajo: un sistema sin
 * cliente verificado se declara desactivado, y NUNCA devuelve ceros.
 */
class RegistroIntegracionesTest extends TestCase
{
    public function test_los_seis_sistemas_estan_catalogados(): void
    {
        $todas = app(RegistroIntegraciones::class)->todas();

        foreach (Sistema::cases() as $sistema) {
            $this->assertArrayHasKey($sistema->value, $todas);
        }

        $this->assertCount(6, $todas);
    }

    public function test_un_sistema_no_habilitado_se_declara_desactivado(): void
    {
        config(['integraciones.activas' => []]);

        $integracion = app(RegistroIntegraciones::class)->para(Sistema::SigNoz);

        $estado = $integracion->estadoLocal();

        $this->assertSame(EstadoIntegracion::Desactivada->value, $estado->estado->value);
        $this->assertNotEmpty($estado->mensaje, 'Desactivado sin explicar por que no sirve de nada.');
    }

    public function test_una_integracion_desactivada_no_devuelve_datos_inventados(): void
    {
        config(['integraciones.activas' => []]);

        $integracion = app(RegistroIntegraciones::class)->para(Sistema::Coolify);

        // Aunque se la llame, una integracion desactivada no construye datos.
        $this->assertSame([], $integracion->consultar());
        $this->assertSame([], $integracion->estadoLocal()->datos);
    }

    public function test_habilitar_sin_credencial_no_alcanza_para_consultar(): void
    {
        // El sistema esta en la lista de activas y `habilitada` en true, pero
        // sin URL ni clave: el estado correcto es "sin configurar", nunca
        // "disponible".
        config([
            'integraciones.activas' => ['uptime_kuma'],
            'integraciones.uptime_kuma.habilitada' => true,
            'integraciones.uptime_kuma.url' => null,
            'integraciones.uptime_kuma.clave_api' => null,
        ]);

        $estado = app(RegistroIntegraciones::class)->para(Sistema::UptimeKuma)->estadoLocal();

        $this->assertSame(EstadoIntegracion::NoConfigurada->value, $estado->estado->value);
    }
}