<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoIntegracion;
use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Ultimo estado conocido de una integracion para una PyME.
 *
 * Es memoria del panel, no del sistema externo: sirve para que el cliente
 * vea "Kopia: no disponible desde las 14:20" y no un cero falso.
 */
class IntegrationState extends Model
{
    use HasUuids;
    use PerteneceATenant;

    protected $table = 'estados_de_integracion';

    protected $fillable = [
        'tenant_id',
        'sistema',
        'estado',
        'motivo',
        'consultado_en',
        'ultimo_exito_en',
        'intentos',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoIntegracion::class,
            'consultado_en' => 'immutable_datetime',
            'ultimo_exito_en' => 'immutable_datetime',
            'intentos' => 'integer',
        ];
    }
}