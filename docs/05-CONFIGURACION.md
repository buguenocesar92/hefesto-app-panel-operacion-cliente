# 05 — Configuración

Todo se configura por variables de entorno. La plantilla esta en
[`.env.example`](../.env.example). Este documento explica que hace cada grupo y
las decisiones que se tomaron.

## Aplicación

| Variable | Por defecto | Nota |
|---|---|---|
| `APP_KEY` | — | **Obligatoria.** `php artisan key:generate` |
| `APP_ENV` | `produccion` | |
| `APP_DEBUG` | `false` | **Nunca `true` en producción.** Con debug activo, Laravel puede incluir detalle interno en las respuestas de error |
| `APP_TIMEZONE` | `America/Santiago` | Importante: las marcas de tiempo se muestran en hora de Chile |

## Cache y sistemas externos

`PANEL_TTL_<SISTEMA>` — tiempo de vida de la cache, en segundos. Por defecto:

| Sistema | TTL | Umbral de "datos antiguos" |
|---|---|---|
| Uptime Kuma | 120 s (2 min) | 600 s |
| SigNoz | 300 s (5 min) | 1800 s |
| Kopia | 900 s (15 min) | 3600 s |
| Coolify | 300 s (5 min) | 1800 s |
| CrowdSec | 300 s (5 min) | 1800 s |
| Authentik | 900 s (15 min) | 3600 s |

**Por que estos valores.** Un cliente no necesita saber si su sitio estuvo
arriba hace diez segundos. Y golpear siete sistemas en cada recarga es
justamente lo que hay que evitar. El umbral de "datos antiguos" esta siempre
por encima del TTL: si la cache se refresca cada diez minutos y el umbral es
de diez, la tarjeta casi nunca llega a mostrarse antigua.

**Cuidado con subir el TTL de Kopia o Authentik.** Si el TTL supera el umbral,
el panel nunca marca esos datos como antiguos, y el cliente puede creer que ve
información en vivo. La regla: **umbral siempre mayor que TTL**.

`PANEL_TIMEOUT_SEGUNDOS` (5 s) — espera maxima contra un sistema externo. Un
backing service lento no puede dejar en blanco la pantalla del cliente.

## Integraciones

`PANEL_INTEGRACIONES_ACTIVAS` — lista separada por comas de los sistemas que
esta instalación decide consultar. **Vacia por defecto**: una instalación nueva
no consulta nada hasta que alguien decide, de forma consciente, que sistema
abre y con que clave.

Las credenciales van en el gestor de secretos del despliegue. Nunca en el
código, ni en la base, ni en un archivo versionado. Ver `docs/02-INTEGRACIONES.md`
para el detalle de cada sistema y su estado de verificación.

## Sobre la facturacion

**El panel no tiene facturacion, y es una decisión, no un forgot.**

El modulo "Uso" no emite facturas, no muestra saldos, no calcula cobros ni
muestra un historial de pagos. La respuesta de la API trae
`"facturacion": null` y una nota que lo explica.

**Por que:**

1. **Hefesto ya tiene un sistema de facturacion** (kit `hefesto-libredte-facturacion`).
   Duplicarlo en el panel significaria dos cifras distintas del mismo mes, y el
   cliente creeria en la que le conviene.
2. **Cobrar es una operación de escritura.** Este panel es de solo lectura por
   regla del proyecto. Un sistema de cobro aquí contradiria la arquitectura.
3. **El dato contable no viene de los sistemas de observabilidad.** No sale de
   Uptime Kuma ni de CrowdSec ni de Coolify: sale de la facturacion.

Lo que si muestra el modulo es **uso**: que sistema esta_reportando, desde
cuando, y si tiene datos. Cuando se conecte una integración de uso (por ejemplo,
consumo de recursos desde Coolify), esos indicadores aparecen ahi.

**Si Hefesto llegara a querer mostrar facturas en el panel, la via correcta es
una integración de solo lectura contra el sistema de facturacion**, con el mismo
tratamiento que las otras: credencial acotada, desactivada hasta verificarse, y
con el mismo tono honesto en pantalla cuando no haya dato.

## Cookies y sesión

| Ajuste | Valor recomendado | Donde |
|---|---|---|
| `HttpOnly` | `true` | Ya lo impone la aplicación |
| `Secure` | `true` | Proxy, ver `docs/04` |
| `SameSite` | `Strict` | Proxy, ver `docs/04` |
| Vigencia del token | 30 dias | `PANEL_VIGENCIA_TOKEN_DIAS` |

La cookie se llama `panel_token`. El token nunca se devuelve en el HTML ni en
las respuestas del servidor de Nuxt al navegador.

## Ajustes con efecto de seguridad que conviene revisar

- `APP_DEBUG`: debe quedar en `false` fuera de desarrollo.
- `DB_SSLMODE`: `require` contra una base remota. Nunca `disable`.
- `UPTIME_KUMA_VERIFICAR_TLS`: `true` salvo contra un servidor local.
- Puertos publicados: en producción, ninguno. PostgreSQL y Redis no se
  publican nunca, ni siquiera en desarrollo con enlace externo.