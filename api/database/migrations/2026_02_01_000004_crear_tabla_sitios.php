<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogo de sitios del cliente. Lo mantiene Hefesto, no el cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sitios', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $tabla->string('nombre', 120);
            // Host sin esquema y sin ruta: "tienda.cl". Es lo que se busca
            // dentro de las etiquetas de las metricas externas.
            $tabla->string('host', 190);
            $tabla->boolean('activo')->default(true);
            $tabla->string('notas', 255)->nullable();
            $tabla->timestampsTz();

            $tabla->unique(['tenant_id', 'host']);
            $tabla->index(['tenant_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sitios');
    }
};