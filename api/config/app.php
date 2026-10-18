<?php

declare(strict_types=1);

/*
| El framework de Laravel LEE estas claves por su nombre estandar. No se
| traducen: "env", "debug" y "key" tienen que quedar exactamente asi para
| que Laravel funcione. El idioma de Chile va en comentarios y mensajes.
*/

return [
    'name' => env('APP_NAME', 'Panel de Operacion del Cliente'),

    'env' => env('APP_ENV', 'produccion'),

    // En produccion debe quedar en false. Con debug activo, Laravel puede
    // incluir detalle interno en las respuestas de error.
    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost'),

    'timezone' => env('APP_TIMEZONE', 'America/Santiago'),

    'locale' => 'es',

    'fallback_locale' => 'es',

    'faker_locale' => 'es_CL',

    'key' => env('APP_KEY'),

    'cipher' => 'AES-256-CBC',

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
    ],

    'providers' => [],
];