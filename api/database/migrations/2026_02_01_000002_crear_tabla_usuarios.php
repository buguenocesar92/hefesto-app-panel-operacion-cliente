<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Usuarios del panel. Cada usuario pertenece a una sola PyME.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $tabla->string('nombre', 120);
            $tabla->string('correo', 190)->unique();
            $tabla->string('clave_hash');
            // Solo dos roles: cliente (lectura) y operador de Hefesto.
            // Un rol mas se agrega cuando exista la necesidad concreta.
            $tabla->string('rol', 32)->default('cliente');
            $tabla->boolean('activo')->default(true);
            $tabla->timestampTz('ultimo_acceso_en')->nullable();
            $tabla->timestampsTz();

            $tabla->index(['tenant_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};