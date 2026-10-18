<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autenticacion por token de acceso. Codigo propio de Hefesto.
 *
 * El token viaja como `Authorization: Bearer <id>.<secreto>`. En la base solo
 * esta el SHA-256 del secreto: comparar hashes, nunca comparar ni devolver
 * el token.
 *
 * Por que no Sanctum ni Passport: el panel es de solo lectura, no necesita
 * refresh tokens ni consentimiento de terceros, y una tabla de tokens escrita
 * por Hefesto es mas facil de auditar que el esquema de otro paquete.
 */
class AutenticarConToken
{
    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $cabecera = (string) $peticion->header('Authorization', '');

        if (! str_starts_with($cabecera, 'Bearer ')) {
            return $this->rechazar('Falta el token de acceso. Inicie sesion en el panel.');
        }

        $token = trim(substr($cabecera, 7));

        $partes = explode('.', $token, 2);

        if (count($partes) !== 2 || $partes[0] === '' || $partes[1] === '') {
            return $this->rechazar('El token de acceso no tiene el formato esperado.');
        }

        [$id, $secreto] = $partes;

        $registro = PersonalAccessToken::query()->find($id);

        if ($registro === null || ! $registro->vigente()) {
            return $this->rechazar('El token de acceso no es valido o esta caducado.');
        }

        if (! hash_equals($registro->hash_secreto, hash('sha256', $secreto))) {
            return $this->rechazar('El token de acceso no es valido o esta caducado.');
        }

        $usuario = User::query()->find($registro->user_id);

        if ($usuario === null || ! $usuario->activo) {
            return $this->rechazar('La cuenta no esta activa.');
        }

        // El usuario se inyecta en la peticion. A partir de aqui, el tenant
        // se deriva de el y de nada mas.
        Auth::setUser($usuario);

        return $siguiente($peticion);
    }

    private function rechazar(string $mensaje): Response
    {
        return response()->json([
            'error' => [
                'codigo' => 401,
                'mensaje' => $mensaje,
            ],
        ], 401);
    }
}