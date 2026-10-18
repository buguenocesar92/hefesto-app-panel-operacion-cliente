<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Configuracion de las integraciones con los sistemas de Hefesto
|--------------------------------------------------------------------------
|
| ESTA ES LA PARTE MAS DELICADA DEL PANEL. Lee las reglas antes de tocar algo:
|
| 1. Cada sistema necesita una credencial de SOLO LECTURA. Si el sistema solo
|    ofrece un token de administrador, la integracion queda DESACTIVADA. Se
|    prefiere una tarjeta "no disponible" a una que exponga el sistema entero.
|
| 2. Las credenciales vienen SIEMPRE del gestor de secretos (variables de
|    entorno inyectadas por el despliegue). Nunca se escriben en el codigo,
|    ni en la base, ni en un archivo versionado.
|
| 3. `habilitada` en true NO significa "verificada". Cada sistema declara su
|    propia evidencia en `verificacion`. Si no hay fuente, la integracion no
|    se habilita, por mucho que el operador la encienda.
|
| Ver docs/02-INTEGRACIONES.md para el detalle de cada sistema.
|
*/

return [
    /*
    | Lista de sistemas-integraciones habilitadas en esta instalacion.
    | Vacio por defecto: una instalacion nueva no consulta nada hasta que
    | alguien decide, de forma consciente, que sistema abre y con que clave.
    */
    'activas' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PANEL_INTEGRACIONES_ACTIVAS', ''))
    ))),

    'uptime_kuma' => [
        'habilitada' => (bool) env('UPTIME_KUMA_HABILITADA', false),
        // Clave de API del endpoint de metricas Prometheus (/metrics).
        // Segun la documentacion oficial, esta clave NO da acceso a la
        // interfaz web de Uptime Kuma: solo permite leer metricas.
        'url' => env('UPTIME_KUMA_URL'),
        'clave_api' => env('UPTIME_KUMA_CLAVE_API'),
        'verificar_tls' => (bool) env('UPTIME_KUMA_VERIFICAR_TLS', true),
        'verificacion' => [
            'estado' => 'parcial',
            'fuente' => 'https://github.com/louislam/uptime-kuma/wiki/Prometheus-API-Keys',
            'verificado' => 'Endpoint /metrics protegido por clave de API y autenticacion basica con usuario vacio.',
            'pendiente' => 'Los nombres y etiquetas de las metricas no estan documentados. Por eso el panel NO inventa nombres: busca, entre las series devueltas, las que contengan el host del sitio del cliente.',
        ],
    ],

    'signoz' => [
        'habilitada' => false,
        'url' => env('SIGNOZ_URL'),
        'clave_api' => env('SIGNOZ_CLAVE_API'),
        'verificacion' => [
            'estado' => 'no_verificada',
            'fuente' => null,
            'verificado' => null,
            'pendiente' => 'No se pudo confirmar en documentacion oficial un endpoint de lectura ni una credencial de solo lectura. Integracion desactivada.',
        ],
    ],

    'kopia' => [
        'habilitada' => false,
        'url' => env('KOPIA_URL'),
        'usuario' => env('KOPIA_USUARIO'),
        'clave' => env('KOPIA_CLAVE'),
        'verificacion' => [
            'estado' => 'no_verificada',
            'fuente' => null,
            'verificado' => null,
            'pendiente' => 'El servidor de Kopia autentica con una cuenta de servidor, que tambien puede iniciar o borrar respaldos. Hasta verificar que exista una credencial restringida a lectura, no se usa.',
        ],
    ],

    'coolify' => [
        'habilitada' => false,
        'url' => env('COOLIFY_URL'),
        'token_api' => env('COOLIFY_TOKEN_API'),
        'verificacion' => [
            'estado' => 'no_verificada',
            'fuente' => null,
            'verificado' => null,
            'pendiente' => 'No se pudo confirmar que los tokens de la API de Coolify permitan limitar el ambito a solo lectura. Integracion desactivada.',
        ],
    ],

    'crowdsec' => [
        'habilitada' => false,
        'url' => env('CROWDSEC_LAPI_URL'),
        'clave_api' => env('CROWDSEC_CLAVE_API'),
        'verificacion' => [
            'estado' => 'parcial',
            'fuente' => 'https://github.com/crowdsecurity/crowdsec/blob/master/pkg/apiserver/apiserver.go',
            'verificado' => 'Existe una API local HTTP (LAPI) con rutas bajo /v1 y autenticacion propia.',
            'pendiente' => 'No se pudieron verificar las rutas exactas de lectura ni una credencial con rutas permitidas. Integracion desactivada.',
        ],
    ],

    'authentik' => [
        'habilitada' => false,
        'url' => env('AUTHENTIK_URL'),
        'token_api' => env('AUTHENTIK_TOKEN_API'),
        'verificacion' => [
            'estado' => 'no_verificada',
            'fuente' => null,
            'verificado' => null,
            'pendiente' => 'No se pudo confirmar una credencial con ambito de solo lectura. Un token de administrador queda descartado por regla del proyecto. Integracion desactivada.',
        ],
    ],
];