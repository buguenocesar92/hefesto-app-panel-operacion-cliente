# 10 — Pruebas y estado

## Estado: código escrito, sin ejecutar

En el entorno donde se construyo este panel:

- **No habia Docker.** No se construyo ninguna imagen, no se levanto el stack.
- **No habia PHP ni Composer.** No se instalaron dependencias, no se ejecuto
  `php artisan`, **no se corrio ninguna prueba**.
- **No habia los sistemas externos** (Uptime Kuma, SigNoz, Kopia, Coolify,
  CrowdSec, Authentik). Ninguna integración se conecto a nada real.
- **Si habia Node v24.21.0**, pero no se ejecuto `npm install` ni
  `npm run build`.

**En consecuencia: este panel no ha sido probado en absoluto.** No hay un solo
test ejecutado, ni una migración aplicada, ni una respuesta de API observada.
Lo que hay es código escrito con cuidado y razonado.

**Y no esta listo para producción.** Es un primer corte.

## Lo que SI se hizo de verdad

### 1. Verificación documental (lectura de fuentes, sin ejecutar nada)

Única actividad real de verificación, y consistio en **leer fuentes
originales**. Lo que resulto:

| Sistema | Fuente leida | Resultado |
|---|---|---|
| Uptime Kuma | Wiki oficial, seccion "Prometheus API Keys" | **Verificado**: endpoint `/metrics`, autenticación básica con usuario vacio, y la clave "cannot be used to access the web interface" |
| CrowdSec | `pkg/apiserver/apiserver.go` del repositorio | **Verificado**: existe API local con rutas `/v1`, con middlewares de autenticación propios. **No** se verificaron las rutas exactas |
| Nuxt | Registro de npm, `nuxt/latest` | **Verificado**: la versión mas reciente es **4.6.2**, y exige Node `^22.22.3 \|\| ^24.15.0 \|\| >=26.0.0` |

Lo que **no** se pudo leer, y por eso sus integraciones quedaron apagadas:

| Fuente | Resultado |
|---|---|
| Documentacion de CrowdSec (seccion de API local) | Ruta inexistente: el sitio devolvio "Page Not Found" |
| Documentacion de Coolify (`api-reference`) | Ruta inexistente: "Page not found" |
| Documentacion de Authentik (seccion de contrasenas de aplicación) | La ruta `.md` no existe; devolvio una pagina 404 |
| Repositorio de Kopia (documentacion del servidor y esquema OpenAPI) | Las rutas consultadas devolvieron 404 |
| Código de metricas de Uptime Kuma | El archivo consultado devolvio 404 en los dos probados |

Se intento también la API de GitHub para listar directorios: respondio 404 en
todos los casos, probablemente bloqueada en este entorno.

**Conclusion honesta:** con las fuentes que se pudieron leer, solo Uptime Kuma
tiene elementos suficientes para un cliente de lectura, y aun así queda
desactivado porque falta confirmar el esquema de sus metricas.

### 2. Barrido de caracteres CJK y cirilicos

Se ejecuto un barrido sobre **todos** los archivos del proyecto, en **dos
pasadas**, buscando bloques CJK y alfabetos cirilico. El objetivo es que no se
colen caracteres de otro idioma en código, comentarios o documentacion.

Resultado de la segunda pasada: limpio. Durante la escritura se detectaron y
corregieron dos caracteres CJK y dos palabras en ingles que se habian colado en
comentarios del código y de la documentacion, antes de este barrido.

## Lo que esta escrito y NO se ha probado

| Componente | Que falta probarlo |
|---|---|
| Migraciones (8) | Que se apliquen sobre PostgreSQL 16 |
| RLS de PostgreSQL | **Lo mas importante**: que las políticas bloqueen de verdad una fuga entre empresas |
| Semilla de ejemplo | Que corra y que los datos se vean |
| Suite `AISlamiento` (8 pruebas) | Toda, incluida la de RLS, que en SQLite se omite |
| `DegradacionEleganteTest` (5) | Toda |
| `SoloLecturaTest` (3) | Toda |
| `NoExponeDatosSensiblesTest` (4) | Toda |
| `ConsultaCacheadaTest` (4) | Toda |
| `LectorPrometheusTest` (7) | Toda |
| `RegistroIntegracionesTest` (4) | Toda |
| Frontend Nuxt | Que compile, que el SSR funcione, que las paginas pinten |
| Imagenes Docker | Que construyan |
| `entrypoint.api.sh` | Que la espera a PostgreSQL funcione |
| Compose (desarrollo y producción) | Que levanten |

**Total: 35 pruebas escritas, 0 ejecutadas.**

## Riesgo técnico concreto: las pruebas de RLS

La suite `AISlamiento` corre en SQLite en memoria, porque así corre en cualquier
maquina sin levantar una base. **SQLite no implementa seguridad de fila.** La
prueba que valida RLS se salta sola en ese motor:

```php
if (DB::getDriverName() !== 'pgsql') {
    $this->markTestSkipped('Requiere PostgreSQL: SQLite no implementa seguridad de fila.');
}
```

Eso es deliberado: **preferimos una prueba omitida y declarada a una prueba que
aparenta pasar sin haber probado nada.**

Las otras siete pruebas de la suite (alcance global, API, cache) si corren en
SQLite, porque no dependen de RLS.

**La primera vez que haya PostgreSQL disponible, esto es lo que hay que correr:**

```bash
docker compose -f deploy/compose.desarrollo.yaml up -d postgres
docker compose -f deploy/compose.desarrollo.yaml run --rm \
  -e DB_CONNECTION=pgsql -e DB_HOST=postgres \
  api php artisan test --testsuite=Aislamiento
```

Si alguna de esas pruebas falla, es un hallazgo de seguridad, no un error de
pruebas. Y hay que arreglarlo antes de mostrar el panel a un cliente.

## Lista de verificación para la primera ejecución real

En orden, porque las ultimas no tienen sentido si las anteriores fallan.

1. `composer install` y `php artisan --version`.
2. `php artisan test` en SQLite. Debe pasar todo menos la prueba de RLS (omitida).
3. Levantar PostgreSQL y correr la suite de aislamiento **contra PostgreSQL**.
4. `php artisan migrate --seed` sobre PostgreSQL limpio.
5. Levantar el compose de desarrollo completo.
6. `curl http://127.0.0.1:8000/api/salud`.
7. Emitir un token de prueba y recorrer los cinco modulos.
8. Crear una segunda empresa y comprobar que no se ven sus datos.
9. `npm install && npm run build` en `web/`.
10. Solo entonces: habilitar Uptime Kuma contra una instancia real y confirmar
    el cruce de etiquetas.

## Pendientes de integraciones

Resumen; el detalle esta en `docs/02-INTEGRACIONES.md`.

| Sistema | Que falta |
|---|---|
| Uptime Kuma | Confirmar el esquema de metricas en una instancia real |
| Kopia | Confirmar si existe un rol de solo lectura |
| CrowdSec | Confirmar rutas de lectura y credencial con rutas permitidas |
| SigNoz | Endpoint de lectura + credencial acotada |
| Coolify | Confirmar si el token admite ambito de solo lectura |
| Authentik | Confirmar si un rol restringido alcanza para leer el estado |

## Pendientes de producto

1. **No hay pagina de acceso.** El token se emite a mano. La integración con
   Authentik resolveria esto, pero primero hay que verificar su credencial.
2. **No hay alimentacion de la tabla `respaldos`.** El historial llega de un
   proceso que aun no existe.
3. **No hay limite de peticiones** en las rutas del panel.
4. **El rol `operador` existe en el esquema pero no tiene comportamiento
   definido en código.**
5. **No hay política de retencion** para `respaldos`.
6. **Las versiones de las imagenes no estan fijadas por digest.**
7. **No hay pruebas del frontend.**

## Como leer este panel

Este primer corte tiene una forma deliberada: **pocas integraciones, todas
honestas**. Preferimos un panel que muestre tres tarjetas y siete "desactivada"
con su motivo, antes que uno con seis tarjetas llenas de ceros que el cliente
tomaria por un respaldo exitoso. La operación invisible es un problema; la
operación que miente es peor.

---

## Los dos roles de PostgreSQL: escritos, no ejecutados

La separación entre el rol de arranque (`panel_operacion_admin`) y el rol de la
aplicación (`panel_operacion`) está escrita en `compose.produccion.yaml`, en
`deploy/postgres/inicializar-rol.sh` y en
[07-SEGURIDAD-Y-AISLAMIENTO.md](07-SEGURIDAD-Y-AISLAMIENTO.md). **No se ha
ejecutado ninguna vez.**

En el equipo donde se escribió este cambio no había PostgreSQL ni Docker. Queda
pendiente:

1. Levantar el `compose` con el volumen de PostgreSQL vacío y `DB_DATABASE`
   declarado, y confirmar que el rol `panel_operacion` se crea sin error.
2. Confirmar con `SELECT rolname, rolsuper, rolbypassrls FROM pg_roles` que el
   rol de la conexión no es superusuario ni salta la RLS.
3. Correr a mano las tres consultas de comprobación de la sección anterior,
   porque esta aplicación **no tiene comando `verificar-aislamiento`**.
4. Probar el escenario que motiva todo el cambio: una empresa viendo los
   agregados de otra.

El punto 3 es una diferencia real con el resto de las aplicaciones de Hefesto y
es el que más conviene no pasar por alto: aquí el aislamiento depende de que
alguien mire. Lo único verificado es estático: `bash -n` pasa sobre el script y
el `compose` es YAML válido, ambas cosas comprobadas por `tools/validar_apps.py`.