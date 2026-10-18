<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Contadores agregados de seguridad, por dia.
 *
 * aqui NO hay IPs, ni agentes de usuario, ni rutas, ni cargas utiles de los
 * ataques. Una direccion IP identifica a una persona y es dato personal: el
 * panel guarda y muestra cantidades, no identidades.
 *
 * Cuando CrowdSec se integre, este modelo recibira los totales que la API
 * entregue. La forma de la tabla ya esta lista para ese dia.
 */
class SecurityCounter extends Model
{
    use HasUuids;
    use PerteneceATenant;

    protected $table = 'contadores_de_seguridad';

    protected $fillable = [
        'tenant_id',
        'dia',
        'detectados',
        'bloqueados',
        'por_tipo',
    ];

    protected function casts(): array
    {
        return [
            'dia' => 'immutable_date',
            'detectados' => 'integer',
            'bloqueados' => 'integer',
            'por_tipo' => 'array',
        ];
    }

    /** @return array<string, mixed> */
    public function resumen(): array
    {
        return [
            'dia' => $this->dia->toDateString(),
            'detectados' => $this->detectados,
            'bloqueados' => $this->bloqueados,
            'por_tipo' => $this->por_tipo ?? [],
        ];
    }
}