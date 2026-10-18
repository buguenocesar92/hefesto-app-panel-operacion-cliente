<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El panel es de SOLO LECTURA. Esta clase lo comprueba.
 *
 * La regla no se apoya en que "no escribamos codigo de escritura": se apoya
 * en que cualquier metodo distinto de GET sea rechazado, en dos capas
 * (public/index.php y el middleware ResolverTenant).
 */
class SoloLecturaTest extends TestCase
{
    use RefreshDatabase;

    public static function metodosQueDebenRechazarse(): array
    {
        return [
            'crear' => ['post'],
            'actualizar' => ['put'],
            'parche' => ['patch'],
            'borrar' => ['delete'],
        ];
    }

    /** @dataProvider metodosQueDebenRechazarse */
    public function test_rechaza_peticiones_que_modifican_datos(string $metodo): void
    {
        $empresa = $this->crearEmpresaConSitio('empresa-propia');

        $respuesta = $this->conTokenDe($empresa['usuario'])->{$metodo}('/api/panel/resumen');

        $respuesta->assertStatus(405);
    }

    public function test_la_api_no_declara_ninguna_ruta_de_escritura(): void
    {
        $rutas = collect(app('router')->getRoutes())->map(function ($ruta): string {
            return implode(',', $ruta->methods());
        });

        foreach ($rutas as $metodos) {
            $this->assertDoesNotMatchRegularExpression(
                '/\b(POST|PUT|PATCH|DELETE)\b/',
                $metodos,
                'El panel no debe declarar rutas que modifiquen datos.'
            );
        }
    }

    public function test_el_resumen_declara_que_no_puede_ejecutar_acciones(): void
    {
        $empresa = $this->crearEmpresaConSitio('empresa-propia');

        $respuesta = $this->conTokenDe($empresa['usuario'])->getJson('/api/panel/resumen');

        $politicas = $respuesta->json('politicas');

        $this->assertTrue($politicas['solo_lectura']);
        $this->assertFalse($politicas['puede_iniciar_respaldo']);
        $this->assertFalse($politicas['puede_reiniciar_servicio']);
        $this->assertFalse($politicas['puede_ver_contenido_de_respaldos']);
    }
}