<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Memoria del panel: como termino la ultima consulta a cada sistema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estados_de_integracion', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $tabla->string('sistema', 32);
            $tabla->string('estado', 24);
            // Motivo en texto plano, pero SIEMPRE generico. Nunca se escribe
            // ahi la respuesta cruda del sistema externo: podria traer una
            // URL interna, un nombre de contenedor o un valor de entorno.
            $tabla->string('motivo', 255)->nullable();
            $tabla->timestampTz('consultado_en')->nullable();
            $tabla->timestampTz('ultimo_exito_en')->nullable();
            $tabla->unsignedInteger('intentos')->default(0);
            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'sistema']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estados_de_integracion');
    }
};