<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EstadoIntegracion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Degradacion elegante: si un sistema no responde, el resto del panel sigue.
 *
 * Este es el requisito que mas veces se rompe en un tablero: un backing
 * service caido termina dejando la pantalla en blanco, y el cliente concluye
 * que Hefesto dejo de mirarlo. Aqui se comprueba lo contrario.
 */
class DegradacionEleganteTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_resumen_siempre_devuelve_una_tarjeta_por_sistema(): void
    {
        $empresa = $this->crearEmpresaConSitio('empresa-propia');

        $respuesta = $this->conTokenDe($empresa['usuario'])->getJson('/api/panel/resumen');

        $respuesta->assertOk();

        $tarjetas = $respuesta->json('datos.tarjetas');

        $this->assertCount(6, $tarjetas, 'Debe haber una tarjeta por cada sistema integrado.');

        foreach ($tarjetas as $tarjeta) {
            $this->assertArrayHasKey('estado', $tarjeta);
            $this->assertArrayHasKey('actualizado_en', $tarjeta);
            $this->assertFalse($tarjeta['escritura_posible']);
        }
    }

    public function test_un_sistema_que_no_responde_no_tira_abajo_el_resumen(): void
    {
        $empresa = $this->crearEmpresaConSitio('empresa-propia');

        // Uptime Kuma queda habilitado y configurado, pero el servidor no
        // responde: es el caso real de un backing service caido.
        config([
            'integraciones.activas' => ['uptime_kuma'],
            'integraciones.uptime_kuma.habilitada' => true,
            'integraciones.uptime_kuma.url' => 'http://uptime-kuma.interno',
            'integraciones.uptime_kuma.clave_api' => 'clave-de-prueba',
        ]);

        Http::fake([
            '*' => fn () => throw new ConnectionException('no hay nadie escuchando'),
        ]);

        $respuesta = $this->conTokenDe($empresa['usuario'])->getJson('/api/panel/resumen');

        $respuesta->assertOk();

        $porSistema = collect($respuesta->json('datos.tarjetas'))->keyBy('sistema');

        // La que falla se declara caida...
        $this->assertSame(
            EstadoIntegracion::NoDisponible->value,
            $porSistema['uptime_kuma']['estado']
        );

        // ...y las demas siguen presentes y utilizables.
        $this->assertCount(6, $porSistema);

        $kopia = $porSistema['kopia'];
        $this->assertTrue($kopia['es_degradado']);
        $this->assertSame(EstadoIntegracion::Desactivada->value, $kopia['estado']);
    }

    public function test_una_integracion_desactivada_no_devuelve_ceros_falsos(): void
    {
        $empresa = $this->crearEmpresaConSitio('empresa-propia');

        $respuesta = $this->conTokenDe($empresa['usuario'])->getJson('/api/panel/resumen');

        $kopia = collect($respuesta->json('datos.tarjetas'))->firstWhere('sistema', 'kopia');

        // Con la integracion apagada, la tarjeta dice "desactivada" y explica
        // por que. Jamas "disponible con cero respaldos".
        $this->assertSame(EstadoIntegracion::Desactivada->value, $kopia['estado']);
        $this->assertNotEmpty($kopia['mensaje']);
    }

    public function test_sin_datos_de_respaldo_el_panel_lo_dice_y_no_inventa(): void
    {
        $empresa = $this->crearEmpresaConSitio('empresa-propia');

        $respuesta = $this->conTokenDe($empresa['usuario'])->getJson('/api/panel/respaldos');

        $respuesta->assertOk();

        $this->assertTrue($respuesta->json('datos.sin_registros'));
        $this->assertNull($respuesta->json('datos.ultimo_respaldo'));
        $this->assertSame([], $respuesta->json('datos.respaldos'));
    }

    public function test_el_pagina_de_salud_es_publico_y_no_revela_nada(): void
    {
        $respuesta = $this->getJson('/api/salud');

        $respuesta->assertOk();
        $respuesta->assertJsonPath('solo_lectura', true);

        // No debe incluir el estado de los sistemas: permitiria mapear la
        // infraestructura sin tener cuenta.
        $respuesta->assertJsonMissingPath('sistemas');
    }
}