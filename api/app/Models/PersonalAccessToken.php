<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Token de acceso del panel.
 *
 * En la base solo se guarda el SHA-256 del secreto: si alguien lee esta
 * tabla, no puede autenticarse con lo que encuentra ahi.
 *
 * El token que viaja es `<id>.<secreto>`. El id permite localizar la fila sin
 * recorrerla, y el secreto es lo que se compara en hash.
 */
class PersonalAccessToken extends Model
{
    use HasUuids;

    protected $table = 'tokens_de_acceso';

    protected $fillable = [
        'user_id',
        'nombre',
        'hash_secreto',
        'ultimo_uso_en',
        'caduca_en',
        'revocado_en',
    ];

    protected $hidden = [
        'hash_secreto',
    ];

    protected function casts(): array
    {
        return [
            'ultimo_uso_en' => 'immutable_datetime',
            'caduca_en' => 'immutable_datetime',
            'revocado_en' => 'immutable_datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function vigente(): bool
    {
        if ($this->revocado_en !== null) {
            return false;
        }

        if ($this->caduca_en !== null && $this->caduca_en->isPast()) {
            return false;
        }

        return true;
    }
}