<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EstadoRespaldo;
use App\Enums\EstadoVerificacion;
use App\Models\BackupRun;
use App\Models\SecurityCounter;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Semilla con una PyME de ejemplo.
 *
 * IMPORTANTE: son datos DE EJEMPLO. No describen una empresa real, no tienen
 * volumen real y no deben leerse como metricas de una operacion. Sirven para
 * levantar el panel en desarrollo y para que las pruebas tengan algo contra
 * que comparar.
 *
 * Crea DOS empresas a proposito: la segunda existe para que el test de
 * aislamiento tenga contra quien fallar. Un panel multitenant probado con una
 * sola empresa no prueba nada.
 */
class ClienteEjemploSeeder extends Seeder
{
    public function run(): void
    {
        $primera = Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'slug' => 'panaderia-los-volcanes',
            'nombre' => 'Panaderia Los Volcanes (ejemplo)',
            'razon_social' => 'Panaderia Los Volcanes SpA',
            'rut' => '76.000.000-0 (ejemplo)',
            'correo_contacto' => 'contacto@ejemplo-panaderia.cl',
            'activo' => true,
        ]);

        $segunda = Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'slug' => 'comercializadora-andes',
            'nombre' => 'Comercializadora Andes (ejemplo)',
            'razon_social' => 'Comercializadora Andes Ltda.',
            'rut' => '77.000.000-0 (ejemplo)',
            'correo_contacto' => 'contacto@ejemplo-comercializadora.cl',
            'activo' => true,
        ]);

        User::query()->create([
            'tenant_id' => $primera->id,
            'nombre' => 'Usuario de ejemplo',
            'correo' => 'cliente@ejemplo-panaderia.cl',
            'clave_hash' => bcrypt('contrasena-de-ejemplo-cambiar'),
            'rol' => 'cliente',
            'activo' => true,
        ]);

        User::query()->create([
            'tenant_id' => $segunda->id,
            'nombre' => 'Usuario de ejemplo',
            'correo' => 'cliente@ejemplo-comercializadora.cl',
            'clave_hash' => bcrypt('contrasena-de-ejemplo-cambiar'),
            'rol' => 'cliente',
            'activo' => true,
        ]);

        $this->sitiosDeEjemplo($primera, ['tienda.ejemplo-panaderia.cl', 'pedidos.ejemplo-panaderia.cl']);
        $this->sitiosDeEjemplo($segunda, ['catalogo.ejemplo-comercializadora.cl']);

        $this->respaldosDeEjemplo($primera);
        $this->seguridadDeEjemplo($primera);
        $this->seguridadDeEjemplo($segunda);
    }

    /**
     * @param  list<string>  $hosts
     */
    private function sitiosDeEjemplo(Tenant $tenant, array $hosts): void
    {
        foreach ($hosts as $indice => $host) {
            Site::query()->create([
                'tenant_id' => $tenant->id,
                'nombre' => 'Sitio '.($indice + 1).' (ejemplo)',
                'host' => $host,
                'activo' => true,
                'notas' => null,
            ]);
        }
    }

    private function respaldosDeEjemplo(Tenant $tenant): void
    {
        // Un respaldo con errores y verificacion fallida, para que se vea el
        // caso malo en la interfaz y no solo el feliz.
        $casos = [
            ['estado' => EstadoRespaldo::Exitoso, 'verificacion' => EstadoVerificacion::Verificado, 'archivos' => 1240, 'bytes' => 912_000_000],
            ['estado' => EstadoRespaldo::Exitoso, 'verificacion' => EstadoVerificacion::Verificado, 'archivos' => 1258, 'bytes' => 924_000_000],
            ['estado' => EstadoRespaldo::Fallido, 'verificacion' => EstadoVerificacion::Fallido, 'archivos' => null, 'bytes' => null],
            ['estado' => EstadoRespaldo::Exitoso, 'verificacion' => EstadoVerificacion::Pendiente, 'archivos' => 1261, 'bytes' => 931_000_000],
        ];

        foreach ($casos as $indice => $caso) {
            $iniciado = now()->subHours(($indice + 1) * 6);

            BackupRun::query()->create([
                'tenant_id' => $tenant->id,
                'origen' => 'kopia (ejemplo)',
                'iniciado_en' => $iniciado,
                'terminado_en' => $iniciado->copy()->addMinutes(9),
                'estado' => $caso['estado'],
                'verificacion' => $caso['verificacion'],
                'verificado_en' => $caso['verificacion'] === EstadoVerificacion::Verificado
                    ? $iniciado->copy()->addMinutes(10)
                    : null,
                'archivos' => $caso['archivos'],
                'bytes' => $caso['bytes'],
                'duracion_segundos' => 540,
            ]);
        }
    }

    private function seguridadDeEjemplo(Tenant $tenant): void
    {
        for ($dia = 6; $dia >= 0; $dia--) {
            SecurityCounter::query()->create([
                'tenant_id' => $tenant->id,
                'dia' => now()->subDays($dia)->toDateString(),
                'detectados' => random_int(0, 40),
                'bloqueados' => random_int(0, 38),
                'por_tipo' => [
                    'http-probing' => random_int(0, 12),
                    'http-bruteforce' => random_int(0, 6),
                    'ssh-bruteforce' => random_int(0, 3),
                ],
            ]);
        }
    }
}