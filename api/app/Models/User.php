<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Usuario del panel. Siempre pertenece a una unica PyME.
 *
 * Esta clase NO usa el alcance de PerteneceATenant a proposito: para
 * autenticar hay que encontrar al usuario por correo ANTES de saber que
 * tenant tiene. La excepcion es segura porque la lectura por correo solo
 * ocurre dentro del middleware de autenticacion, y en cuanto el usuario
 * esta autenticado, ResolverTenant ya fijo el tenant.
 */
class User extends Model implements AuthenticatableContract
{
    use Authenticatable;
    use HasUuids;

    protected $table = 'usuarios';

    protected $fillable = [
        'tenant_id',
        'nombre',
        'correo',
        'clave_hash',
        'rol',
        'activo',
        'ultimo_acceso_en',
    ];

    protected $hidden = [
        'clave_hash',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'ultimo_acceso_en' => 'immutable_datetime',
            'clave_hash' => 'hashed',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(PersonalAccessToken::class);
    }

    /** Perfil publico que la API devuelve. Nunca incluye el tenant_id. */
    public function perfil(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'correo' => $this->correo,
            'rol' => $this->rol,
            'ultimo_acceso_en' => $this->ultimo_acceso_en?->toIso8601String(),
        ];
    }
}