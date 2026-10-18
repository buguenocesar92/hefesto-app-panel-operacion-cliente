<?php

declare(strict_types=1);

namespace Tests;

use App\Models\PersonalAccessToken;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\ContextoTenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Base de las pruebas del panel.
 *
 * Aporta lo que TODA prueba de aislamiento necesita: crear una empresa, su
 * usuario y su token, y entrar con el tenant ya resuelto.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Crea una empresa con un sitio y un usuario, y devuelve los tres.
     *
     * @return array{tenant: Tenant, usuario: User, sitio: Site}
     */
    protected function crearEmpresaConSitio(string $slug): array
    {
        $tenant = Tenant::query()->create([
            'slug' => $slug,
            'nombre' => 'Empresa '.$slug,
            'activo' => true,
        ]);

        $usuario = User::query()->create([
            'tenant_id' => $tenant->id,
            'nombre' => 'Usuario de '.$slug,
            'correo' => $slug.'@ejemplo.cl',
            'clave_hash' => bcrypt('contrasena-de-prueba'),
            'rol' => 'cliente',
            'activo' => true,
        ]);

        $sitio = Site::query()->create([
            'tenant_id' => $tenant->id,
            'nombre' => 'Sitio de '.$slug,
            'host' => $slug.'.ejemplo.cl',
            'activo' => true,
        ]);

        return ['tenant' => $tenant, 'usuario' => $usuario, 'sitio' => $sitio];
    }

    /** Token de acceso listo para usar en la cabecera Authorization. */
    protected function tokenDe(User $usuario): string
    {
        $secreto = 'secreto-de-prueba-'.bin2hex(random_bytes(8));

        $registro = PersonalAccessToken::query()->create([
            'user_id' => $usuario->id,
            'nombre' => 'prueba',
            'hash_secreto' => hash('sha256', $secreto),
            'caduca_en' => now()->addDay(),
        ]);

        return $registro->id.'.'.$secreto;
    }

    /** Ejecuta una operacion con el tenant fijo, como lo haria el middleware. */
    protected function comoTenant(Tenant $tenant, \Closure $operacion): mixed
    {
        return app(ContextoTenant::class)->ejecutarAislado((string) $tenant->id, $operacion);
    }

    /** Autentica a un usuario del panel con su token de acceso. */
    protected function conTokenDe(User $usuario): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->tokenDe($usuario));
    }
}