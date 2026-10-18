<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogo de Pymes clientes de Hefesto.
 *
 * Es la EXCEPCION consciente al aislamiento: no lleva RLS porque se consulta
 * durante el inicio de sesion, cuando todavia no hay tenant de sesion. Por eso
 * la aplicacion nunca la expone cruda (ver migracion ..._000008, que crea la
 * vista `vw_tenants_publicos`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $tabla): void {
            $tabla->uuid('id')->primary();
            $tabla->string('slug', 48)->unique();
            $tabla->string('nombre', 160);
            $tabla->string('razon_social', 160)->nullable();
            $tabla->string('rut', 16)->nullable();
            $tabla->string('correo_contacto', 190)->nullable()->unique();
            $tabla->boolean('activo')->default(true);
            $tabla->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};