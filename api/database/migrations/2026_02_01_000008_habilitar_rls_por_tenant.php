<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seguridad de fila (RLS) de PostgreSQL: la ultima linea del aislamiento.
 *
 * Por que RLS y no solo el alcance de Eloquent: el alcance protege las
 * consultas que pasan por los modelos. RLS protege TODAS, incluidas las que
 * alguien escriba a mano en una vista, en un comando artisan o en un
 * console de Postgres con la misma credencial de la app.
 *
 * COMO FUNCIONA
 * -------------
 * Las politicas comparan `tenant_id` con la variable de sesion
 * `app.tenant_id`, que escribe ContextoTenant con `set_config(..., true)`
 * (ambito de transaccion). Si la variable no esta fijada, `current_setting`
 * devuelve null y la comparacion da falso: la consulta devuelve cero filas.
 * Fallar cerrando es la unica falla aceptable en este panel.
 *
 * En SQLite (pruebas en memoria) esto no existe: la migracion se omite y las
 * pruebas que dependen de RLS se saltan solas. No se declaran probadas.
 */
return new class extends Migration
{
    /** Tablas con datos de clientes. */
    private const TABLAS_PROTEGIDAS = [
        'sitios',
        'respaldos',
        'contadores_de_seguridad',
        'estados_de_integracion',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION public.app_tenant_id() RETURNS uuid
            LANGUAGE sql STABLE AS $$
                SELECT NULLIF(current_setting('app.tenant_id', true), '')::uuid
            $$;
        SQL);

        foreach (self::TABLAS_PROTEGIDAS as $tabla) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }

            DB::unprepared(sprintf('ALTER TABLE %s ENABLE ROW LEVEL SECURITY', $tabla));
            DB::unprepared(sprintf('ALTER TABLE %s FORCE ROW LEVEL SECURITY', $tabla));

            DB::unprepared(sprintf(
                'CREATE POLICY %s on %s USING (tenant_id = public.app_tenant_id()) WITH CHECK (tenant_id = public.app_tenant_id())',
                $tabla.'_politica_tenant',
                $tabla
            ));
        }

        // Esta vista es la UNICA forma de leer el catalogo de Pymes sin
        // sesion de tenant, y expone solo lo justo para el inicio de sesion.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE VIEW public.vw_tenants_publicos AS
                SELECT id, slug, nombre, activo
                FROM public.tenants
                WHERE activo = true;
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP VIEW IF EXISTS public.vw_tenants_publicos');

        foreach (self::TABLAS_PROTEGIDAS as $tabla) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }

            DB::unprepared(sprintf('DROP POLICY IF EXISTS %s ON %s', $tabla.'_politica_tenant', $tabla));
        }

        DB::unprepared('DROP FUNCTION IF EXISTS public.app_tenant_id()');
    }
};