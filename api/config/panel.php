<?php

declare(strict_types=1);

return [
    /*
    |-------------------------------------------------------------------------
    | Parametros del panel
    |-------------------------------------------------------------------------
    | TTL de cache por sistema, en segundos. Son generosos a proposito: un
    | cliente no necesita datos de hace diez segundos, y golpear siete
    | sistemas en cada recarga es exactamente lo que hay que evitar.
    |
    | El frontend SIEMPRE muestra la hora de la ultima actualizacion, para
    | que nadie crea que lo que ve esta en vivo.
    */
    'ttl_por_sistema' => [
        'uptime_kuma' => (int) env('PANEL_TTL_UPTIME_KUMA', 120),
        'signoz' => (int) env('PANEL_TTL_SIGNOZ', 300),
        'kopia' => (int) env('PANEL_TTL_KOPIA', 900),
        'coolify' => (int) env('PANEL_TTL_COOLIFY', 300),
        'crowdsec' => (int) env('PANEL_TTL_CROWDSEC', 300),
        'authentik' => (int) env('PANEL_TTL_AUTHENTIK', 900),
    ],

    // Cuanto pasa despues del TTL para marcar la tarjeta como "datos viejos".
    'umbral_datos_viejos' => [
        'uptime_kuma' => (int) env('PANEL_VIEJO_UPTIME_KUMA', 600),
        'signoz' => (int) env('PANEL_VIEJO_SIGNOZ', 1800),
        'kopia' => (int) env('PANEL_VIEJO_KOPIA', 3600),
        'coolify' => (int) env('PANEL_VIEJO_COOLIFY', 1800),
        'crowdsec' => (int) env('PANEL_VIEJO_CROWDSEC', 1800),
        'authentik' => (int) env('PANEL_VIEJO_AUTHENTIK', 3600),
    ],

    // Tiempo maximo de espera contra un sistema externo. Un backing service
    // lento no puede dejar en blanco la pantalla del cliente.
    'timeout_segundos' => (int) env('PANEL_TIMEOUT_SEGUNDOS', 5),

    // Cuantos respaldos y cuantos dias de seguridad se devuelven por defecto.
    'respaldos_por_peticion' => (int) env('PANEL_RESPALDOS_PETICION', 30),
    'dias_seguridad_por_defecto' => 7,
    'dias_seguridad_maximo' => 90,
];