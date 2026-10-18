<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Sitio del cliente.
 *
 * OJO: esto NO es el monitoreo. Es el catalogo que Hefesto mantiene del
 * cliente (nombre y host). Los datos de disponibilidad vienen de las
 * integraciones; este registro solo dice a que sitio pertenece cada dato.
 */
class Site extends Model
{
    use HasUuids;
    use PerteneceATenant;

    protected $table = 'sitios';

    protected $fillable = [
        'tenant_id',
        'nombre',
        'host',
        'activo',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}