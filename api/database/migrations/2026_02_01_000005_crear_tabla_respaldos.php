<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de respaldos con indicadores agregados.
 *
 * NO hay columna para contenido, rutas, nombres de archivo ni hashes de cada
 * archivo. Es una decision de diseno, no una falta: ver
 * docs/07-SEGURIDAD-Y-AISLAMIENTO.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('respaldos', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $tabla->string('origen', 60);
            $tabla->timestampTz('iniciado_en');
            $tabla->timestampTz('terminado_en')->nullable();
            $tabla->string('estado', 24);
            $tabla->string('verificacion', 24)->default('pendiente');
            $tabla->timestampTz('verificado_en')->nullable();
            $tabla->unsignedBigInteger('archivos')->nullable();
            $tabla->unsignedBigInteger('bytes')->nullable();
            $tabla->unsignedInteger('duracion_segundos')->nullable();
            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'iniciado_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('respaldos');
    }
};