<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contadores diarios de seguridad. Agregados, sin IPs ni cargas utiles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contadores_de_seguridad', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $tabla->date('dia');
            $tabla->unsignedInteger('detectados')->default(0);
            $tabla->unsignedInteger('bloqueados')->default(0);
            // Mapa tipo -> cantidad del dia. Sin IP, sin ruta, sin carga util.
            $tabla->jsonb('por_tipo')->nullable();
            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'dia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contadores_de_seguridad');
    }
};