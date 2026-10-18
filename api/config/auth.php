<?php

declare(strict_types=1);

/*
| El panel autentica con un token propio (ver App\Http\Middleware\
| AutenticarConToken). Laravel igual necesita que este archivo declare los
| guardas por defecto, porque es el guard que se instancia para poder
| inyectar al usuario en la peticion.
|
| El panel no usa ni registro ni recuperacion de contrasena: las cuentas las
| crea Hefesto. Por eso no hay proveedor de contrasenas configurado.
*/

return [
    'defaults' => [
        'guard' => 'web',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'usuarios',
        ],
    ],

    'providers' => [
        'usuarios' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
    ],

    'passwords' => [],

    'vigencia_token_dias' => (int) env('PANEL_VIGENCIA_TOKEN_DIAS', 30),
];