<?php

declare(strict_types=1);

/*
| "default", "connections" y "migrations" los lee Laravel con esos nombres
| exactos. El nombre de la conexion principal es "pgsql" porque el panel
| depende de la seguridad de fila (RLS) de PostgreSQL: ver la migracion
| 2026_02_01_000008_habilitar_rls_por_tenant.php.
|
| "sqlite" existe solo para las pruebas automatizadas, que corren en
| memoria. SQLite no tiene RLS, asi que las pruebas que dependen de las
| politicas se omiten solas en ese motor: no se declaran probadas.
*/

return [
    'default' => env('DB_CONNECTION', 'pgsql'),

    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'panel_operacion'),
            'username' => env('DB_USERNAME', 'panel_operacion'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            // Verificar el certificado del servidor en una base remota.
            // Nunca sslmode=disable contra un host que no sea local.
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],

        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', ':memory:'),
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ],

    'migrations' => [
        'table' => 'migraciones',
        'update_date_on_publish' => true,
    ],
];