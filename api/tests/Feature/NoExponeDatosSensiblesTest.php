<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EstadoRespaldo;
use App\Enums\EstadoVerificacion;
use App\Models\BackupRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El panel nunca debe devolver contenido sensible.
 *
 * El riesgo aqui no es un bug puntual: es que alguien, en el futuro, agregue un
 * campo util ("por que no mostramos el log del respaldo") y con eso abra el
 * contenido de los datos del cliente. Esta prueba es la red para ese dia.
 */
class NoExponeDatosSensiblesTest extends TestCase
{
    use RefreshDatabase;

    /** Palabras que jamas deben aparecer como clave de la respuesta JSON. */
    private const CLAVES_PROHIBIDAS = [
        'password', 'clave_hash', 'token', 'secreto', 'secret',
        'correo', 'email', 'ip', 'direccion_ip', 'ruta_archivo',
        'variable_entorno', 'env', 'contenido', 'archivo',
    ];

    public function test_las_claves_de_la_api_no_incluyen_campos_sensibles(): void
    {
        $empresa = $this->crearEmpresaConSitio('empresa-propia');

        $respuesta = $this->conTokenDe($empresa['usuario'])->getJson('/api/panel/resumen');

        $this->assertSinClavesProhibidas($respuesta->json());
    }

    public function test_un_respaldo_se_entrega_solo_como_indicadores_agregados(): void
    {
        $empresa = $this->crearEmpresaConSitio('empresa-propia');

        $this->comoTenant($empresa['tenant'], function () use ($empresa): void {
            BackupRun::query()->create([
                'tenant_id' => $empresa['tenant']->id,
                'origen' => 'kopia (prueba)',
                'iniciado_en' => now()->subHour(),
                'terminado_en' => now(),
                'estado' => EstadoRespaldo::Exitoso,
                'verificacion' => EstadoVerificacion::Verificado,
                'archivos' => 1240,
                'bytes' => 912_000_000,
            ]);
        });

        $respuesta = $this->conTokenDe($empresa['usuario'])->getJson('/api/panel/respaldos');

        $respuesta->assertOk();

        $respaldo = $respuesta->json('datos.ultimo_respaldo');

        // Lo que el cliente ve: el numero de archivos, no los archivos.
        $this->assertSame(1240, $respaldo['archivos']);

        // Y no hay ninguna clave que pudiera contener contenido o una ruta.
        $this->assertSinClavesProhibidas($respaldo);
    }

    public function test_el_panel_de_seguridad_solo_entrega_cantidades(): void
    {
        $empresa = $this->crearEmpresaConSitio('empresa-propia');

        $respuesta = $this->conTokenDe($empresa['usuario'])->getJson('/api/panel/seguridad');

        $respuesta->assertOk();

        $json = $respuesta->json();

        $this->assertArrayHasKey('totales', $json['datos']);
        $this->assertArrayHasKey('detectados', $json['datos']['totales']);
        $this->assertSinClavesProhibidas($json);
    }

    public function test_el_usuario_no_puede_ver_datos_de_otra_empresa_por_el_correo(): void
    {
        $propia = $this->crearEmpresaConSitio('empresa-propia');
        $ajena = $this->crearEmpresaConSitio('empresa-ajena');

        $respuesta = $this->conTokenDe($propia['usuario'])->getJson('/api/panel/resumen');

        // El correo de la otra empresa es dato de contacto, no indicador.
        $respuesta->assertDontSee($ajena['usuario']->correo);
    }

    /**
     * @param  mixed  $datos
     */
    private function assertSinClavesProhibidas(mixed $datos): void
    {
        foreach ((array) $datos as $clave => $valor) {
            $minuscula = mb_strtolower((string) $clave);

            foreach (self::CLAVES_PROHIBIDAS as $prohibida) {
                $this->assertStringNotContainsString(
                    $prohibida,
                    $minuscula,
                    sprintf('La clave "%s" no debe existir en la respuesta de la API.', $clave)
                );
            }

            if (is_array($valor)) {
                $this->assertSinClavesProhibidas($valor);
            }
        }
    }
}