<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Cache\ConsultaCacheada;
use App\Services\Integraciones\RegistroIntegraciones;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RegistroIntegraciones::class);

        // El servicio de cache del panel usa el almacen "panel", separado de
        // la cache de framework: vaciar consultas no debe tocar rutas, vistas
        // compiladas ni configuracion.
        $this->app->singleton(Repository::class, fn (): Repository => ConsultaCacheada::almacen());

        $this->app->singleton(ConsultaCacheada::class, fn ($app): ConsultaCacheada => new ConsultaCacheada(
            $app->make(Repository::class)
        ));
    }

    public function boot(): void
    {
        //
    }
}