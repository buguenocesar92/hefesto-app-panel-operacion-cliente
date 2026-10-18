<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tokens de acceso del panel (codigo propio de Hefesto).
 *
 * Se guarda `hash_secreto` (SHA-256), nunca el token. El indice unico sobre
 * el hash no es solo para buscar rapido: tambien hace imposible tener dos
 * tokens con el mismo secreto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tokens_de_acceso', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('user_id')->constrained('usuarios')->cascadeOnDelete();
            $tabla->string('nombre', 80);
            $tabla->string('hash_secreto', 64)->unique();
            $tabla->timestampTz('ultimo_uso_en')->nullable();
            $tabla->timestampTz('caduca_en')->nullable();
            $tabla->timestampTz('revocado_en')->nullable();
            $tabla->timestampsTz();

            $tabla->index(['user_id', 'revocado_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tokens_de_acceso');
    }
};