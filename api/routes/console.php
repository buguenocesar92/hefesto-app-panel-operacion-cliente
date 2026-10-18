<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
|
| El panel NO tiene trabajos que escriban en los sistemas externos. Solo
| refresca su propia cache de consultas, para que un cambio de TTL no
| dependa de que alguien recargue la pantalla.
|
| Se ejecuta con `php artisan schedule:work` o con el cron del contenedor
| (ver deploy/compose.produccion.yaml).
*/

Schedule::command('panel:refrescar-cache')->everyTenMinutes()->withoutOverlapping();