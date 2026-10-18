<?php

declare(strict_types=1);

/*
| "default" y "stores" los lee Laravel con esos nombres exactos.
|
| Redis es obligatorio: las consultas a los sistemas externos se cachean aca
| para que un refresco de pantalla no golpee los siete sistemas a la vez.
|
| El almacen "panel" esta separado a proposito: ahi viven las consultas
| cacheadas con su marca de tiempo, y se puede vaciar entero sin tocar la
| cache de framework (configuracion, rutas, vistas compiladas).
*/

return [
    'default' => env('CACHE_STORE', 'redis'),

    'stores' => [
        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
        ],

        'panel' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
        ],
    ],

    'prefix' => env('CACHE_PREFIX', 'panel_operacion_cliente_'),
];