<?php

declare(strict_types=1);

namespace Tests\Feature\AISlamiento;

use App\Enums\Sistema;
use App\Models\BackupRun;
use App\Models\Site;
use App\Services\Cache\ConsultaCacheada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * PRUEBA MAESTRA DEL PROYECTO: el aislamiento entre empresas.
 *
 * Cada test parte de la misma situacion: dos Pymes con datos propios y un
 * usuario autenticado en la primera que intenta alcanzar los datos de la
 * segunda.
 *
 * Se prueban varias maneras de filtrar, porque una sola no alcanza:
 *  1. La API por id de recurso.
 *  2. El listado completo (que podria devolver filas mezcladas).
 *  3. Una consulta directa al modelo (un endpoint futuro que olvide el filtro).
 *  4. La cache compartida.
 *  5. La seguridad de fila de PostgreSQL (SQL escrito a mano).
 *
 * QUE SE ESPERA EN CADA CASO: que la peticion falle o devuelva cero filas.
 * Nunca que devuelva el dato de la otra empresa, ni siquiera por error.
 */
class AislamientoEntreEmpresasTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_puede_ver_el_sitio_de_otra_empresa_por_id(): void
    {
        $propia = $this->crearEmpresaConSitio('empresa-propia');
        $ajena = $this->crearEmpresaConSitio('empresa-ajena');

        $respuesta = $this->conTokenDe($propia['usuario'])
            ->getJson('/api/panel/sitios/'.$ajena['sitio']->id);

        // 404 y no 403: un 403 confirmaria que ese id existe, y con eso ya
        // habriamos mostrado informacion de la otra PyME.
        $respuesta->assertNotFound();
        $respuesta->assertJsonMissing(['id' => $ajena['sitio']->id]);
    }

    public function test_el_listado_de_sitios_no_trae_sitios_de_otra_empresa(): void
    {
        $propia = $this->crearEmpresaConSitio('empresa-propia');
        $ajena = $this->crearEmpresaConSitio('empresa-ajena');

        $respuesta = $this->conTokenDe($propia['usuario'])->getJson('/api/panel/sitios');

        $respuesta->assertOk();

        $hosts = array_column($respuesta->json('datos.sitios'), 'host');

        $this->assertContains($propia['sitio']->host, $hosts);
        $this->assertNotContains($ajena['sitio']->host, $hosts);
    }

    public function test_una_consulta_directa_al_modelo_no_ve_la_otra_empresa(): void
    {
        $propia = $this->crearEmpresaConSitio('empresa-propia');
        $ajena = $this->crearEmpresaConSitio('empresa-ajena');

        $sitios = $this->comoTenant($propia['tenant'], fn (): array => Site::query()->pluck('host')->all());

        $this->assertContains($propia['sitio']->host, $sitios);
        $this->assertNotContains($ajena['sitio']->host, $sitios);
    }

    public function test_los_respaldos_de_otra_empresa_no_aparecen_en_el_historial(): void
    {
        $propia = $this->crearEmpresaConSitio('empresa-propia');
        $ajena = $this->crearEmpresaConSitio('empresa-ajena');

        $this->comoTenant($propia['tenant'], function () use ($propia): void {
            BackupRun::query()->create([
                'tenant_id' => $propia['tenant']->id,
                'origen' => 'kopia (prueba)',
                'iniciado_en' => now()->subHours(3),
                'terminado_en' => now()->subHours(3),
                'estado' => 'exitoso',
                'verificacion' => 'verificado',
                'archivos' => 10,
            ]);
        });

        $this->comoTenant($ajena['tenant'], function () use ($ajena): void {
            BackupRun::query()->create([
                'tenant_id' => $ajena['tenant']->id,
                'origen' => 'secreto-de-la-otra-empresa',
                'iniciado_en' => now()->subHours(2),
                'terminado_en' => now()->subHours(2),
                'estado' => 'exitoso',
                'verificacion' => 'verificado',
                'archivos' => 99,
            ]);
        });

        $respuesta = $this->conTokenDe($propia['usuario'])->getJson('/api/panel/respaldos');

        $respuesta->assertOk();

        // La cadena de la otra empresa no puede aparecer en esta respuesta.
        // Asercion de contenido, no de forma.
        $respuesta->assertDontSee('secreto-de-la-otra-empresa');
        $this->assertCount(1, $respuesta->json('datos.respaldos'));
    }

    public function test_sin_tenant_resuelto_no_se_devuelve_ninguna_fila(): void
    {
        $this->crearEmpresaConSitio('empresa-propia');

        // Sin pasar por ContextoTenant, el alcance global lanza excepcion en
        // vez de devolver filas. Falla cerrado.
        $this->expectException(RuntimeException::class);

        Site::query()->get();
    }

    public function test_la_cache_no_comparte_datos_entre_empresas(): void
    {
        $propia = $this->crearEmpresaConSitio('empresa-propia');
        $ajena = $this->crearEmpresaConSitio('empresa-ajena');

        $cache = app(ConsultaCacheada::class);

        $clavePropia = $this->comoTenant($propia['tenant'], fn (): string => $cache->clave(Sistema::UptimeKuma));
        $claveAjena = $this->comoTenant($ajena['tenant'], fn (): string => $cache->clave(Sistema::UptimeKuma));

        // Dos empresas, dos claves. Si fueran iguales, una veria la cache de
        // la otra: la misma fuga, por otro camino.
        $this->assertNotSame($clavePropia, $claveAjena);
        $this->assertStringContainsString((string) $propia['tenant']->id, $clavePropia);
    }

    public function test_una_peticion_sin_token_no_entrega_nada(): void
    {
        $this->crearEmpresaConSitio('empresa-propia');

        $this->getJson('/api/panel/resumen')->assertUnauthorized();
    }

    /**
     * Seguridad de fila de PostgreSQL: el filtro final, el que sigue valiendo
     * aunque alguien escriba SQL a mano con la credencial de la aplicacion.
     *
     * SQLite (motor de las pruebas por defecto) NO tiene RLS. En vez de fingir
     * que paso, la prueba se omite y queda declarada como pendiente en
     * docs/10-PRUEBAS-Y-ESTADO.md.
     */
    public function test_la_seguridad_de_fila_lo_impide_aunque_se_ignore_la_aplicacion(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped(
                'Requiere PostgreSQL: SQLite no implementa seguridad de fila. '
                .'Ver docs/10-PRUEBAS-Y-ESTADO.md.'
            );
        }

        $propia = $this->crearEmpresaConSitio('empresa-propia');
        $ajena = $this->crearEmpresaConSitio('empresa-ajena');

        // SQL directo, sin pasar por el modelo ni por el alcance global.
        $filas = $this->comoTenant($propia['tenant'], fn (): array => DB::select(
            'SELECT host FROM sitios ORDER BY host'
        ));

        $hosts = array_map(fn (object $fila): string => (string) $fila->host, $filas);

        $this->assertContains($propia['sitio']->host, $hosts);
        $this->assertNotContains($ajena['sitio']->host, $hosts);
    }
}