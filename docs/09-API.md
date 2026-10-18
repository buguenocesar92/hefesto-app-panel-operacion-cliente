# 09 — API

Base: `/api`. Todas las rutas son **de lectura**. No existe una sola ruta de
escritura, y hay dos capas que rechazan cualquier metodo distinto de GET.

## Autenticación

```
Authorization: Bearer <id>.<secreto>
```

El token lo emite Hefesto (`docs/06-PRIMER-CLIENTE.md`). En la base solo esta
el SHA-256 del secreto. El navegador nunca lo ve: el servidor de Nuxt lo toma
de la cookie `panel_token` y lo agrega aquí.

Respuestas de autenticación:

| Código | Causa |
|---|---|
| 401 | Falta el token, tiene mal formato, esta caducado, revocado, o la cuenta esta inactiva |
| 403 | La cuenta no esta asociada a ninguna empresa |

## Envoltura

Respuesta correcta:

```json
{ "datos": { "...": "..." } }
```

Respuesta de error:

```json
{ "error": { "codigo": 404, "mensaje": "El sitio no existe." } }
```

Los errores nunca incluyen trazas ni detalle interno: el manejador de
excepciones de `bootstrap/app.php` devuelve un mensaje generico para
cualquier fallo no previsto.

## Endpoints

### `GET /api/salud` — público

Comprobacion de vida. **No** incluye el estado de los sistemas: permitiria
mapear la infraestructura de Hefesto sin tener cuenta.

```json
{
  "estado": "ok",
  "aplicacion": "Panel de Operacion del Cliente",
  "hora": "2026-02-10T15:04:11-03:00",
  "modo": "produccion",
  "solo_lectura": true
}
```

### `GET /api/panel/resumen` — modulo Resumen

Una tarjeta por sistema. Devuelve **siempre las seis**, aunque esten apagadas.

```json
{
  "datos": {
    "generado_en": "2026-02-10T15:04:11-03:00",
    "tarjetas": [
      {
        "sistema": "uptime_kuma",
        "nombre": "Uptime Kuma",
        "que_aporta": "Disponibilidad de sus sitios.",
        "estado": "desactivada",
        "estado_texto": "Desactivada",
        "es_degradado": true,
        "requiere_atencion": false,
        "mensaje": "Integracion apagada en esta instalacion. ...",
        "actualizado_en": null,
        "desde_cache": true,
        "verificacion_integracion": "parcial",
        "escritura_posible": false
      }
    ]
  },
  "politicas": {
    "solo_lectura": true,
    "puede_iniciar_respaldo": false,
    "puede_reiniciar_servicio": false,
    "puede_ver_contenido_de_respaldos": false,
    "nota": "Este panel no ejecuta acciones sobre los sistemas de Hefesto. ..."
  }
}
```

### `GET /api/panel/integraciones` — estado por sistema

Lo mismo, mas el motivo, la fuente de verificación y que queda pendiente. Pensado
para que Hefesto vea que falta.

### `GET /api/panel/sitios` — modulo Sitios

```json
{
  "datos": {
    "origen": {
      "sistema": "uptime_kuma",
      "estado": "desactivada",
      "actualizado_en": null
    },
    "sitios": [
      {
        "id": "…",
        "nombre": "Tienda en linea",
        "host": "tienda.ejemplo.cl",
        "disponibilidad": "sin_datos",
        "disponibilidad_texto": "Sin informacion disponible",
        "series_relacionadas": 0,
        "nota": "El sistema de monitoreo no entrego datos que coincidan con este sitio."
      }
    ]
  }
}
```

`sin_datos` no es "caido". Son cosas distintas.

### `GET /api/panel/sitios/{sitio}`

Detalle de un sitio. Un id de **otra** empresa devuelve **404**, no 403: un 403
confirmaria que ese id existe.

### `GET /api/panel/respaldos?limite=30` — modulo Respaldos

`limite` va entre 1 y 100. Por defecto, `PANEL_RESPALDOS_PETICION` (30).

```json
{
  "datos": {
    "sin_registros": false,
    "ultimo_respaldo": {
      "id": "…",
      "origen": "kopia (ejemplo)",
      "iniciado_en": "2026-02-10T09:00:00-03:00",
      "terminado_en": "2026-02-10T09:09:00-03:00",
      "estado": "exitoso",
      "estado_texto": "Completado",
      "verificacion": "verificado",
      "verificacion_texto": "Verificado",
      "verificado_en": "2026-02-10T09:10:00-03:00",
      "archivos": 1240,
      "bytes": 912000000,
      "duracion_segundos": 540
    },
    "respaldos": ["…"]
  }
}
```

Sin registros: `sin_registros: true` y `ultimo_respaldo: null`.

### `GET /api/panel/seguridad?dias=7` — modulo Seguridad

`dias` va entre 1 y 90. Devuelve cantidades agregadas. **Nunca** direcciones IP
ni detalle de ataques.

### `GET /api/panel/uso` — modulo Uso

Estado de los recursos por sistema de origen. `facturacion` viaja en `null`,
siempre con su nota. Ver `docs/05-CONFIGURACION.md` para por que no hay
facturacion.

## Códigos de estado

| Código | Significado |
|---|---|
| 200 | Correcto |
| 401 | Token ausente, invalido o caducado |
| 403 | La cuenta no pertenece a ninguna empresa |
| 404 | El recurso no existe **o no es de esta empresa** |
| 405 | Metodo distinto de GET. El panel es de solo lectura |
| 500 | Error interno. El mensaje es generico; el detalle queda en el registro del servidor |

## Promesa de contrato

Esta API no mostrara, en ninguna versión:

- Contenido de respaldos, ni rutas, ni nombres de archivo.
- Correos, IPs, agentes de usuario ni cargas utiles de ataques.
- Tokens, claves, variables de entorno ni configuración de los sistemas internos.
- Resultados de operaciones de escritura, porque no existen.

Hay una prueba que vigila la forma de la respuesta
(`tests/Feature/NoExponeDatosSensiblesTest.php`). La vigilancia real sigue
siendo la revision.