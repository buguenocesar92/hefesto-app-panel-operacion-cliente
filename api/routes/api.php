<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Panel\IntegracionesController;
use App\Http\Controllers\Api\Panel\ResumenController;
use App\Http\Controllers\Api\Panel\RespaldosController;
use App\Http\Controllers\Api\Panel\SeguridadController;
use App\Http\Controllers\Api\Panel\SitiosController;
use App\Http\Controllers\Api\Panel\UsoController;
use App\Http\Controllers\Api\SaludController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de la API
|--------------------------------------------------------------------------
|
| Todas son de lectura. No hay una sola ruta POST, PUT, PATCH o DELETE en
| este archivo, y public/index.php rechaza ademas cualquier metodo distinto
| de GET antes de que la peticion llegue a Laravel.
|
| El middleware "token" autentica; "ResolverTenant" (anexado globalmente en
| bootstrap/app.php) fija el tenant desde el usuario autenticado y no desde
| la peticion.
|
*/

Route::get('/salud', SaludController::class)->name('salud');

Route::middleware('token')->prefix('panel')->name('panel.')->group(function (): void {
    Route::get('/resumen', ResumenController::class)->name('resumen');
    Route::get('/integraciones', IntegracionesController::class)->name('integraciones');
    Route::get('/sitios', SitiosController::class)->name('sitios');
    Route::get('/sitios/{sitio}', [SitiosController::class, 'show'])->name('sitios.ver');
    Route::get('/respaldos', RespaldosController::class)->name('respaldos');
    Route::get('/seguridad', SeguridadController::class)->name('seguridad');
    Route::get('/uso', UsoController::class)->name('uso');
});