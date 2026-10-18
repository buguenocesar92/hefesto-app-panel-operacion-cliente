<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoRespaldo;
use App\Enums\EstadoVerificacion;
use App\Models\Concerns\PerteneceATenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Historial de respaldos. Solo indicadores agregados.
 *
 * NO existe aqui ningun campo para guardar el contenido de un respaldo: ni
 * ruta, ni nombre de archivo, ni checksum, ni usuario. Eso es deliberado y no
 * es una omision pendiente: el panel no debe poder mostrar el contenido de los
 * datos del cliente, ni siquiera por accidente.
 */
class BackupRun extends Model
{
    use HasUuids;
    use PerteneceATenant;

    protected $table = 'respaldos';

    protected $fillable = [
        'tenant_id',
        'origen',
        'iniciado_en',
        'terminado_en',
        'estado',
        'verificacion',
        'verificado_en',
        'archivos',
        'bytes',
        'duracion_segundos',
    ];

    protected function casts(): array
    {
        return [
            'iniciado_en' => 'immutable_datetime',
            'terminado_en' => 'immutable_datetime',
            'verificado_en' => 'immutable_datetime',
            'estado' => EstadoRespaldo::class,
            'verificacion' => EstadoVerificacion::class,
            'archivos' => 'integer',
            'bytes' => 'integer',
            'duracion_segundos' => 'integer',
        ];
    }

    /**
     * Forma que ve el cliente. Solo contadores.
     *
     * @return array<string, mixed>
     */
    public function resumen(): array
    {
        return [
            'id' => $this->id,
            'origen' => $this->origen,
            'iniciado_en' => $this->iniciado_en?->toIso8601String(),
            'terminado_en' => $this->terminado_en?->toIso8601String(),
            'estado' => $this->estado->value,
            'estado_texto' => $this->estado->etiqueta(),
            'verificacion' => $this->verificacion->value,
            'verificacion_texto' => $this->verificacion->etiqueta(),
            'verificado_en' => $this->verificado_en?->toIso8601String(),
            'archivos' => $this->archivos,
            'bytes' => $this->bytes,
            'duracion_segundos' => $this->duracion_segundos,
        ];
    }
}