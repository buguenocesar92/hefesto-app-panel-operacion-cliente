# 04 — Instalación

> **Estado: nada de esto se ha ejecutado.** En el entorno donde se escribio el
> panel no habia Docker, ni PHP, ni Composer. Los comandos de abajo son los que
> se seguirian; ninguno tiene un resultado verificado. Ver
> `docs/10-PRUEBAS-Y-ESTADO.md`.

## Requisitos

| Componente | Versión | Nota |
|---|---|---|
| Docker Engine | 24 o superior | Compose v2 (`docker compose`, no `docker-compose`) |
| PHP (si se corre la API sin contenedor) | 8.3 o superior | Con `pdo_pgsql`, `bcmath`, `intl`, `json`, `openssl` |
| Composer | 2.x | |
| Node (si se corre el frontend sin contenedor) | `^22.22.3` o `^24.15.0` | Exigido por Nuxt 4.6.2 |
| PostgreSQL | 16 | Necesario por la seguridad de fila |

La versión de Node no es una preferencia: Nuxt 4.6.2 declara en su
`package.json` los motores `^22.22.3 || ^24.15.0 || >=26.0.0`. Fuente:
<https://registry.npmjs.org/nuxt/latest> (consultado al escribir este documento).

## Puesta en marcha en desarrollo

```bash
# 1. Configuracion
cp .env.example .env

# 2. Generar los secretos que faltan
#    APP_KEY
php -r 'echo "base64:".base64_encode(random_bytes(32)), PHP_EOL;'
#    DB_PASSWORD y REDIS_PASSWORD
openssl rand -base64 32

# 3. Levantar el stack
docker compose -f deploy/compose.desarrollo.yaml --env-file .env up -d

# 4. Esquema y datos de ejemplo
docker compose -f deploy/compose.desarrollo.yaml exec api php artisan migrate --seed

# 5. Verificar
docker compose -f deploy/compose.desarrollo.yaml exec api php artisan test
```

El panel queda en `http://127.0.0.1:3000` y la API en
`http://127.0.0.1:8000`.

En desarrollo los puertos se publican **solo en loopback**
(`127.0.0.1:3000` y `127.0.0.1:8000`), para que el stack no quede expuesto a la
red de la oficina.

## Puesta en marcha en producción

1. Construir las imagenes en una maquina con red y publicarlas en el registro:

   ```bash
   docker build -f deploy/Dockerfile.api -t hefesto/panel-operacion-api:0.1.0 .
   docker build -f deploy/Dockerfile.web -t hefesto/panel-operacion-web:0.1.0 .
   ```

2. Copiar el código al servidor en una ruta dedicada (por ejemplo
   `/opt/hefesto/panel-operacion`), en `deploy/codigo/api`.

3. Crear el `.env` de producción **desde el gestor de secretos**, no desde
   `.env.example`.

4. Levantar:

   ```bash
   docker compose -f deploy/compose.produccion.yaml --env-file .env up -d
   docker compose -f deploy/compose.produccion.yaml exec api php artisan migrate --force
   ```

5. Publicar TLS en el proxy queandelante (Coolify o el reverse proxy del
   servidor).

### Sobre las versiones de las imagenes

Las imagenes están fijadas a versión mayor y menor: `postgres:16.4-alpine`,
`redis:7.4-alpine`, `php:8.3-fpm-alpine`, `node:24-bookworm-slim`.

**Pendiente:** fijar también el digest SHA de cada imagen. Es lo correcto para
producción, porque una etiqueta puede moverse, pero resolver digests requiere
acceso al registro, que no habia en el entorno donde se escribio esto. Hasta
entonces, revisar las etiquetas antes de cada despliegue.

## Detras del proxy

El frontend corre en el puerto 3000 y la API en el 8000. En producción el
proxy deberia:

- Servir por HTTPS con TLS valido. Nunca desactivar la verificación.
- Enviar el trafico del panel a `web:3000`.
- **No exponer la API directamente**: el proxy de Nuxt ya la consume por la red
  interna. Publicar la API aparte entregaria las respuestas de Laravel sin la
  capa del frontend.
- Fijar los atributos de la cookie del token:

  ```
  Set-Cookie: panel_token=...; HttpOnly; Secure; SameSite=Strict; Path=/
  ```

  `HttpOnly` ya lo impone la aplicación; `Secure` y `SameSite` hay que
  configurarlos en el proxy. Ver el riesgo 7 de `docs/07`.

## La tarea programada

`routes/console.php` define `panel:refrescar-cache`, que refresca la cache cada
diez minutos. Para que corra:

```bash
# En el contenedor de la API
docker compose -f deploy/compose.produccion.yaml exec api \
  php artisan schedule:work
```

O, mejor, con el cron del host:

```
* * * * * cd /opt/hefesto/panel-operacion && docker compose -f deploy/compose.produccion.yaml exec -T api php artisan schedule:run >> /dev/null 2>&1
```

`withoutOverlapping()` evita que dos ejecuciones se pisen. La tarea no escribe
en ningun sistema externo: solo refresca lo que el panel ya tiene cacheado.

## Comprobar el aislamiento

A diferencia de `panel:refrescar-cache`, este comando se corre **a mano**, una
vez, después de instalar:

```bash
docker compose -f deploy/compose.produccion.yaml exec api \
  php artisan panel:verificar-aislamiento
```

Revisa, en orden, que el rol de la conexión no sea superusuario ni tenga
`BYPASSRLS`, que las cuatro tablas de clientes tengan RLS forzada, que sus
políticas comparen contra `public.app_tenant_id()`, y que una consulta sin
empresa en el contexto devuelva cero filas. El último punto cuenta las tablas dos
veces, con y sin empresa en el contexto, para distinguir "la tabla está vacía" de
"la RLS está ocultando datos".

**No escribe nada**, a diferencia del verificador de las aplicaciones verticales:
el panel es de solo lectura. Y **no se ha ejecutado nunca**: en el equipo donde
se escribió no había PostgreSQL ni Docker. Ver
[07-SEGURIDAD-Y-AISLAMIENTO.md](07-SEGURIDAD-Y-AISLAMIENTO.md).

## Diagnóstico

| Sintoma | Causa probable |
|---|---|
| `APP_KEY` vacio al arrancar | Falta `php artisan key:generate` |
| Todas las tarjetas "desactivada" | `PANEL_INTEGRACIONES_ACTIVAS` vacio. Es el estado por defecto |
| Un sitio en "sin información disponible" | Uptime Kuma esta apagado, o las etiquetas no coinciden con el host |
| La API responde 401 | Token ausente, caducado o revocado |
| La API responde 405 | Se mando un metodo distinto de GET. El panel es de solo lectura |
| `could not find driver` | Faltan extensiones de PHP en el contenedor |

## Verificar que quedo todo aislado

Despues del primer despliegue, y cada vez que se toque el aislamiento:

```bash
docker compose -f deploy/compose.produccion.yaml exec api php artisan test --testsuite=Aislamiento
docker compose -f deploy/compose.produccion.yaml exec -e DB_CONNECTION=pgsql php artisan test --testsuite=Aislamiento
```

La segunda es la que importa: corre la suite contra PostgreSQL, y ahi se
ejecuta de verdad la prueba de seguridad de fila, que en SQLite se omite.