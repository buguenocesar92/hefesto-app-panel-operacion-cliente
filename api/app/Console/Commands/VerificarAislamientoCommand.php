<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Tenancy\ContextoTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Comprueba, desde la consola, que el aislamiento entre empresas del panel está
 * puesto de verdad.
 *
 *     php artisan panel:verificar-aislamiento
 *
 * POR QUÉ ESTE COMANDO Y POR QUÉ NO ES COMO EL DE LAS APLICACIONES VERTICALES
 * --------------------------------------------------------------------------
 * El panel sí aísla por `tenant_id`: la migración
 * `2026_02_01_000008_habilitar_rls_por_tenant` pone Row Level Security forzado
 * sobre las cuatro tablas de datos de clientes y las mete detrás de
 * `public.app_tenant_id()`. Por eso el comando tiene sentido acá.
 *
 * Pero el modelo NO es el de las verticales, y el comando se adapta:
 *
 *   - El panel es de SOLO LECTURA. `index.php` rechaza cualquier método que no
 *     sea GET, HEAD u OPTIONS, y `routes/api.php` no declara una sola ruta de
 *     escritura. Este comando NO escribe nada: ni filas de prueba, ni empresas,
 *     ni nada. Una prueba que escribiera datos rompería el propio contrato del
 *     panel y tocaría datos de clientes reales.
 *   - El `tenant_id` del panel no es el de una clínica con fichas de pacientes.
 *     Las tablas detrás de RLS son `sitios`, `respaldos`, `contadores_de_seguridad`
 *     y `estados_de_integracion`: datos operativos y agregados de las Pymes.
 *   - La tabla `tenants` NO tiene RLS, y es a propósito: sin ella no hay
 *     catálogo para el inicio de sesión. La vista `vw_tenants_publicos` tampoco,
 *     y está documentada como tal. Por eso este comando no las revisa, y en
 *     cambio avisa de que existen, y de que no las revisa.
 *
 * LO QUE COMPRUEBA, EN ORDEN
 * --------------------------
 *   1. Que el rol con el que conecta la aplicación NO sea superusuario ni tenga
 *      BYPASSRLS. Va primero, y antes de leer nada.
 *   2. Que las cuatro tablas con `tenant_id` tengan RLS ACTIVADA y FORZADA.
 *   3. Que cada una tenga su política, y que la política compare contra
 *      `public.app_tenant_id()`, con `USING` y con `WITH CHECK`.
 *   4. Que `public.app_tenant_id()` exista y devuelva `null` sin empresa en el
 *      contexto: así el aislamiento falla cerrado.
 *   5. Que una consulta SIN empresa en el contexto devuelva cero filas, y que
 *      ese cero no venga de que la tabla esté vacía: se cuenta también dentro del
 *      contexto de una empresa activa de verdad.
 *
 * NADA DE ESTO SE HA EJECUTADO
 * ----------------------------
 * En el equipo donde se escribió este comando no había PostgreSQL ni Docker, y
 * no hay `php -l` disponible. El código se revisó a mano y no se declara
 * probado: no ha corrido contra ninguna base real. Ver `docs/10`.
 */
class VerificarAislamientoCommand extends Command
{
    protected $signature = 'panel:verificar-aislamiento';

    protected $description = 'Comprueba que el aislamiento entre empresas del panel está puesto y funciona.';

    /**
     * Las mismas tablas de la migración `2026_02_01_000008_habilitar_rls_por_tenant`.
     *
     * Si se agregan tablas nuevas, hay que agregarlas en los dos lados, o este
     * comando pasa por alto la tabla que falta y da verde una instalación sin
     * aislamiento.
     *
     * @var list<string>
     */
    private const TABLAS_PROTEGIDAS = [
        'sitios',
        'respaldos',
        'contadores_de_seguridad',
        'estados_de_integracion',
    ];

    public function handle(): int
    {
        $fallos = 0;

        $this->info('Comprobando el aislamiento entre empresas del panel.');
        $this->line('');

        // El rol va PRIMERO, antes de leer las tablas. Con un rol superusuario o
        // con BYPASSRLS, todo lo que viene después se ejecutaría saltándose las
        // políticas y daría verde sin estar mirando nada.
        $fallos += $this->comprobarRol();
        $fallos += $this->comprobarTablas();
        $fallos += $this->comprobarPoliticas();
        $fallos += $this->comprobarFuncion();
        $fallos += $this->comprobarLecturaCerrada();

        $this->line('');

        if ($fallos > 0) {
            $this->error("Se encontraron {$fallos} problemas. No muestre datos de clientes hasta corregirlos.");
            $this->line('Ver docs/07.');

            return self::FAILURE;
        }

        $this->info('Aislamiento comprobado. Las tablas de clientes están detrás de Row Level Security forzado.');
        $this->line('Ver docs/07.');

        return self::SUCCESS;
    }

    /**
     * QUE EL ROL CONECTADO NO SE SALTE LAS POLÍTICAS
     * -----------------------------------------------
     * Esta comprobación va **primera** y consulta `pg_roles` directamente por
     * `current_user`. A propósito: si el rol equivocado se detectara solo de
     * forma indirecta —leer tablas y ver si se filtran— eso dependería de que
     * haya filas, así que contra una base recién instalada y vacía el conteo
     * daba cero y el comando pasaba en verde sin haber comprobado nada. Los
     * atributos del rol se leen del catálogo de PostgreSQL, y por eso esta
     * comprobación vale igual con la base vacía.
     *
     * Es el fallo más grave y el más silencioso: un superusuario, o un rol con
     * `BYPASSRLS`, ignora todas las políticas de Row Level Security, y
     * `FORCE ROW LEVEL SECURITY` sujeta al dueño de la tabla pero **no** al
     * superusuario. Con ese rol, cada Pyme vería los sitios, los respaldos y los
     * contadores de seguridad de las demás, sin error y sin aviso.
     */
    private function comprobarRol(): int
    {
        try {
            $rol = DB::selectOne(
                'SELECT rolname AS nombre, rolsuper AS superusuario, rolbypassrls AS bypass
                 FROM pg_roles
                 WHERE rolname = current_user'
            );
        } catch (Throwable $e) {
            $this->error('  [FALLA] No se pudo leer el rol de la conexión: ' . $e->getMessage());
            $this->line('          Sin ese dato no se puede afirmar nada sobre el aislamiento.');

            return 1;
        }

        if ($rol === null) {
            $this->error('  [FALLA] No se pudo determinar el rol de la conexión.');

            return 1;
        }

        if ($rol->superusuario) {
            $this->error("  [FALLA] El rol \"{$rol->nombre}\" ES SUPERUSUARIO.");
            $this->line('          Un superusuario ignora TODAS las políticas de Row Level Security.');
            $this->line('          `FORCE ROW LEVEL SECURITY` sujeta al dueño de la tabla, pero NO al');
            $this->line('          superusuario: con este rol el aislamiento entre empresas no existe.');
            $this->line('          Qué hacer: `POSTGRES_USER` debe ser el rol de arranque');
            $this->line('          `panel_operacion_admin`, que la aplicación nunca usa, y el rol de la');
            $this->line('          aplicación tiene que estar creado NOSUPERUSER NOBYPASSRLS en');
            $this->line('          `deploy/postgres/inicializar-rol.sh`.');
            $this->line('          Patrón de referencia: apps/almacen/deploy/postgres/inicializar-rol.sh');

            return 1;
        }

        if ($rol->bypass) {
            $this->error("  [FALLA] El rol \"{$rol->nombre}\" tiene BYPASSRLS.");
            $this->line('          Con BYPASSRLS, PostgreSQL ignora TODAS las políticas de Row Level Security,');
            $this->line('          aunque las tablas tengan `FORCE ROW LEVEL SECURITY` puesto.');
            $this->line('          Qué hacer: el mismo de arriba. `POSTGRES_USER` debe ser el rol de');
            $this->line('          arranque `panel_operacion_admin`, que la aplicación nunca usa, y el rol de');
            $this->line('          la aplicación tiene que estar creado NOSUPERUSER NOBYPASSRLS en');
            $this->line('          `deploy/postgres/inicializar-rol.sh`.');
            $this->line('          Patrón de referencia: apps/almacen/deploy/postgres/inicializar-rol.sh');

            return 1;
        }

        $this->line("  [OK]    El rol \"{$rol->nombre}\" no es superusuario ni tiene BYPASSRLS.");

        return 0;
    }

    /**
     * Que las tablas con `tenant_id` tengan RLS activada y forzada.
     *
     * Sin `FORCE`, el dueño de la tabla se salta la política. Y el rol de la
     * aplicación es dueño de la base justamente para poder correr las
     * migraciones, así que el `FORCE` no es opcional acá.
     */
    private function comprobarTablas(): int
    {
        $fallos = 0;

        foreach (self::TABLAS_PROTEGIDAS as $tabla) {
            $fila = DB::selectOne(
                'SELECT relrowsecurity AS rls, relforcerowsecurity AS forzada
                 FROM pg_class
                 WHERE relname = ? AND relnamespace = \'public\'::regnamespace',
                [$tabla]
            );

            if ($fila === null) {
                $this->error("  [FALLA] La tabla {$tabla} no existe.");
                $this->line('          Sin ella el panel no tiene datos que mostrar, o la migración no corrió.');

                $fallos++;

                continue;
            }

            if (! $fila->rls || ! $fila->forzada) {
                $this->error("  [FALLA] La tabla {$tabla} no tiene RLS forzado.");
                $this->line('          Sin `FORCE ROW LEVEL SECURITY`, el dueño de la tabla se salta la política.');

                $fallos++;
            }
        }

        if ($fallos === 0) {
            $this->line('  [OK]    Las cuatro tablas con tenant_id tienen RLS activada y forzada.');
        }

        return $fallos;
    }

    /**
     * Que cada tabla tenga su política, y que la política serve.
     *
     * Esto importa tanto como el `FORCE`: una política que no compare contra
     * `public.app_tenant_id()` no aísla nada, por muy correctas que estén las
     * tablas. Por eso se lee el catálogo en vez de confiar en la migración.
     */
    private function comprobarPoliticas(): int
    {
        $fallos = 0;

        foreach (self::TABLAS_PROTEGIDAS as $tabla) {
            $politica = DB::selectOne(
                'SELECT policyname AS nombre, qual, with_check AS control
                 FROM pg_policies
                 WHERE schemaname = \'public\' AND tablename = ?',
                [$tabla]
            );

            if ($politica === null) {
                $this->error("  [FALLA] La tabla {$tabla} no tiene ninguna política de RLS.");
                $this->line('          Con RLS activada y sin política, la tabla queda cerrada del todo.');

                $fallos++;

                continue;
            }

            $usa = (string) ($politica->qual ?? '');
            $controla = (string) ($politica->control ?? '');

            if (! str_contains($usa, 'app_tenant_id')) {
                $this->error("  [FALLA] La política {$politica->nombre} de {$tabla} no compara contra app_tenant_id().");
                $this->line('          Una política que no mira la empresa no aísla empresas.');

                $fallos++;
            }

            if (! str_contains($controla, 'app_tenant_id')) {
                $this->error("  [FALLA] La política {$politica->nombre} de {$tabla} no tiene WITH CHECK con app_tenant_id().");
                $this->line('          Sin WITH CHECK, la política deja pasar filas de otra empresa.');

                $fallos++;
            }
        }

        if ($fallos === 0) {
            $this->line('  [OK]    Cada tabla tiene su política, con USING y WITH CHECK contra app_tenant_id().');
        }

        return $fallos;
    }

    /**
     * Que la función de contexto exista y falle cerrada.
     *
     * `app_tenant_id()` devuelve `null` cuando no hay empresa en el contexto, y
     * `null` no es igual a ningún `tenant_id`: la comparación da falso y la
     * consulta no devuelve filas. Eso es lo que hace que el aislamiento falle
     * cerrado. Si devolviera un valor, o si la función no existiera, todo
     * aparecería en todas partes.
     */
    private function comprobarFuncion(): int
    {
        try {
            $existe = DB::selectOne(
                "SELECT 1 AS ok FROM pg_proc WHERE proname = 'app_tenant_id' LIMIT 1"
            );
        } catch (Throwable $e) {
            $this->error('  [FALLA] No se pudo leer el catálogo de funciones: ' . $e->getMessage());

            return 1;
        }

        if ($existe === null) {
            $this->error('  [FALLA] No existe la función public.app_tenant_id().');
            $this->line('          Sin ella, las políticas no tienen contra qué comparar.');

            return 1;
        }

        try {
            $fila = DB::selectOne('SELECT public.app_tenant_id() AS valor');
        } catch (Throwable $e) {
            $this->error('  [FALLA] La función app_tenant_id() lanzó una excepción: ' . $e->getMessage());

            return 1;
        }

        if ($fila === null || $fila->valor !== null) {
            $this->error('  [FALLA] public.app_tenant_id() devolvió un valor sin empresa en el contexto.');
            $this->line('          Con un valor inventado, cada empresa vería los datos de todas las demás.');

            return 1;
        }

        $this->line('  [OK]    app_tenant_id() existe y devuelve null sin empresa: falla cerrado.');

        return 0;
    }

    /**
     * Que una consulta SIN empresa en el contexto no devuelva filas.
     *
     * LEE, NO ESCRIBE. El panel es de solo lectura y este comando lo respeta: no
     * crea filas temporales como hacen las aplicaciones verticales, porque acá eso
     * escribiría datos de clientes reales y rompería el contrato del panel.
     *
     * EL PROBLEMA DE LEER SIN EMPRESA, Y CÓMO SE RESUELVE
     * --------------------------------------------------
     * Leer sin empresa en el contexto tiene que dar cero filas. Pero ese cero es
     * ambiguo: puede significar que la tabla está vacía, o puede significar que la
     * RLS está funcionando y ocultando filas que sí existen. Con los dos casos
     * mezclados, el comando daría verde sin haber comprobado nada.
     *
     * Por eso se cuentan las filas dos veces: una sin empresa en el contexto, que
     * tiene que dar cero, y otra dentro del contexto de una empresa activa de
     * verdad. Si la segunda da cero, la tabla está vacía y el comando lo dice,
     * porque entonces la primera comprobación no prueba nada. Si la segunda da
     * filas, la tabla tiene datos y RLS los está ocultando sin empresa: esa es la
     * evidencia fuerte, y la que en las verticales da la lectura cruzada con
     * filas temporales.
     */
    private function comprobarLecturaCerrada(): int
    {
        $tenantId = $this->primeraEmpresaActiva();

        if ($tenantId === null) {
            $this->line('  [AVISO] No hay ninguna empresa activa, así que no se puede distinguir una tabla');
            $this->line('          vacía de una tabla bien aislada. Este punto no prueba nada.');
            $this->line('          Los cuatro puntos anteriores leen el catálogo y siguen siendo válidos.');

            return 0;
        }

        $fallos = 0;
        $vacias = 0;

        foreach (self::TABLAS_PROTEGIDAS as $tabla) {
            try {
                $sinContexto = DB::table($tabla)->count();
            } catch (Throwable $e) {
                $this->error("  [FALLA] No se pudo contar {$tabla} sin empresa en el contexto: " . $e->getMessage());

                $fallos++;

                continue;
            }

            if ($sinContexto !== 0) {
                $this->error("  [FALLA] Sin empresa en el contexto, {$tabla} devolvió {$sinContexto} fila(s).");
                $this->line('          El aislamiento está abierto: una consulta sin empresa lee datos de clientes.');

                $fallos++;

                continue;
            }

            $conContexto = $this->contarParaEmpresa($tenantId, $tabla);

            if ($conContexto === null) {
                $this->line("  [AVISO] No se pudo contar {$tabla} dentro del contexto de una empresa.");
                $this->line('          Cero filas sin empresa es lo esperado, pero sin la contraprueba');
                $this->line('          no se puede afirmar que la tabla tenga datos.');

                continue;
            }

            if ($conContexto === 0) {
                $vacias++;

                continue;
            }

            $this->line("  [OK]    {$tabla} tiene {$conContexto} fila(s) y sin empresa en el contexto");
            $this->line('          devuelve cero: la RLS está ocultando datos que sí existen.');
        }

        if ($fallos === 0) {
            $this->line('  [OK]    Sin empresa en el contexto, las cuatro tablas devolvieron cero filas.');
        }

        if ($vacias > 0) {
            $this->line("  [AVISO] {$vacias} tabla(s) están realmente vacías. Con la tabla vacía el conteo da cero");
            $this->line('          por falta de datos y no por aislamiento, así que ahí la comprobación no');
            $this->line('          prueba nada. Las revisiones del rol y del catálogo sí son concluyentes');
            $this->line('          con la base vacía. Ver docs/07.');
        }

        return $fallos;
    }

    /**
     * La primera empresa activa, para usarla como contexto de la contraprueba.
     *
     * `tenants` no tiene RLS a propósito: sin ella no hay catálogo para el
     * inicio de sesión. Es la misma lectura que hace `panel:refrescar-cache`.
     */
    private function primeraEmpresaActiva(): ?string
    {
        try {
            $fila = DB::table('tenants')
                ->where('activo', true)
                ->orderBy('id')
                ->first(['id']);
        } catch (Throwable) {
            return null;
        }

        if ($fila === null || $fila->id === null) {
            return null;
        }

        return (string) $fila->id;
    }

    /**
     * Cuenta las filas de una tabla dentro del contexto de una empresa.
     *
     * Va en una transacción porque `set_config(..., true)` es de alcance de
     * transacción: al confirmar, el valor desaparece solo y no contamina la
     * contraprueba de las demás tablas.
     */
    private function contarParaEmpresa(string $tenantId, string $tabla): ?int
    {
        try {
            return DB::transaction(function () use ($tenantId, $tabla): int {
                DB::select('SELECT set_config(?, ?, true)', [ContextoTenant::VARIABLE, $tenantId]);

                return DB::table($tabla)->count();
            });
        } catch (Throwable) {
            return null;
        }
    }
}