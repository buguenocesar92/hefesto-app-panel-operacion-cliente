<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * PyME cliente de Hefesto. En el esquema es `tenant`, que es como lo llama
 * la documentacion tecnica; para el cliente es "la empresa".
 *
 * EXCEPCION CONSCIENTE AL AISLAMIENTO
 * -----------------------------------
 * Esta tabla no lleva seguridad de fila (RLS) porque se consulta antes de
 * que exista una sesion con tenant: es la tabla que permite saber a quien
 * pertenece un correo al iniciar sesion.
 *
 * CORRECCION DE 18-10-2026: este comentario decia antes que "los datos
 * publicos salen de la vista vw_tenants_publicos". Es falso. La vista se
 * crea en la migracion ..._000008 pero ningun codigo la consulta; este
 * modelo lee la tabla directamente. Se detecto al auditar las 17
 * aplicaciones del estudio. Ver docs/03-MODELO-DE-DATOS.md.
 */
class Tenant extends Model
{
    use HasUuids;

    protected $table = 'tenants';

    protected $fillable = [
        'slug',
        'nombre',
        'razon_social',
        'rut',
        'correo_contacto',
        'activo',
    ];

    protected $hidden = [
        'correo_contacto',
        'rut',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function sitios(): HasMany
    {
        return $this->hasMany(Site::class);
    }
}