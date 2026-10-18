# 07 — Seguridad y aislamiento

Este documento lista los riesgos con frases claras. No es un formulario para
marcar: cada punto dice que puede pasar, que tan grave es y que hay que hacer.

## 1. El aislamiento entre empresas — riesgo número uno

**Que es.** El panel muestra datos de varios clientes de Hefesto. Si un cliente
ve datos de otro, es una fuga: no es un error de interfaz, es un incidente.

**Como se controla, en tres capas.**

1. **El alcance global de Eloquent** (`Concerns\PerteneceATenant`) agrega
   `where tenant_id = ?` a toda consulta de un modelo con empresa. Si no hay
   empresa resuelta, **lanza una excepcion** en vez de devolver filas.
2. **La seguridad de fila de PostgreSQL** protege las cuatro tablas con datos
   de clientes, incluso frente a SQL escrito a mano.
3. **La empresa se resuelve desde el usuario autenticado**, nunca desde un
   parámetro de la ruta, del cuerpo ni de una cabecera.

**Por que importa el punto 3.** Si el panel aceptara `?tenant_id=...` en la
peticion, cambiar un valor en la URL bastaria para leer los datos de otra
empresa. La regla es: el identificador de empresa sale del token, y del token
no sale nada mas.

**Que falta y es un riesgo real.**

- **El rol `operador` no esta definido en código.** El campo `rol` existe y la
  base tiene el valor por defecto `cliente`, pero no hay ninguna comprobacion
  que lo use. Hoy todos los usuarios autenticados ven lo de su propia empresa y
  nada mas, que es el comportamiento correcto. Si manana se agrega un rol con
  acceso cruzado, hay que escribir explicitamente la excepcion y probarla.
- **La prueba de aislamiento usa SQLite**, que no tiene RLS. La barrera de base
  de datos **no ha sido ejecutada**. Es el pendiente mas importante de este
  documento.

## 2. Una credencial mal elegida expone un sistema entero

**Que es.** Las integraciones se autentican contra sistemas de Hefesto. Si la
credencial es mas amplia de lo debido, el panel deja de ser un tablero y pasa
a ser una puerta al sistema.

**Por que es grave de verdad.** No es un riesgo teorico de "podrian verse
datos sensibles": si el panel guarda el token de administración de Coolify, ese
token puede **desplegar, reiniciar y destruir** los recursos de todos los
clientes de Hefesto. Lo que falla no es la confidencialidad, es la
disponibilidad de la operación entera.

**Regla aplicada en este primer corte.** Si un sistema solo ofrece un token de
administrador, la integración **no se activa**. No se activa "por ahora" ni "solo
para desarrollo": no se activa. Es lo que dejo apagadas a SigNoz, Kopia,
Coolify y Authentik.

**Riesgo residual que queda:**

- **Uptime Kuma esta verificado a medias.** La clave de metricas no accede a la
  interfaz web (documentado), pero la integración esta **desactivada** porque
  falta confirmar el esquema de las metricas. Mientras este apagada, el riesgo
  es teorico.
- **La credencial vive en variables de entorno.** El gestor de secretos las
  inyecta al desplegar; nunca estan en el código ni en un archivo versionado.
  Aun así, un contenedor con `docker inspect` puede mostrarlas. Ver punto 6.

## 3. Que el panel sea de solo lectura se apoya en dos capas

**Que es.** El panel nunca escribe en los sistemas externos, y tampoco en sus
propios datos de negocio.

**Como se controla.**

1. `api/public/index.php` rechaza cualquier metodo distinto de GET, HEAD u
   OPTIONS **antes** de que la peticion llegue a Laravel.
2. El middleware `ResolverTenant` vuelve a rechazar los metodos de escritura.
3. `routes/api.php` no declara una sola ruta POST, PUT, PATCH o DELETE.
4. `tests/Feature/SoloLecturaTest.php` lo comprueba, incluida una prueba que
   recorre las rutas registradas y falla si aparece un metodo de escritura.

**Por que dos capas.** Una sola capa es una decisión de código. Dos capas
independientes sobreviven a que alguien quite una de ellas por un descuido.

**Riesgo residual:** el metodo HTTP por si solo no garantiza solo lectura. Una
peticion `GET` a un endpoint mal diseñado puede disparar una accion en el
sistema externo. Por eso el contrato `Integracion` no expone metodos de
escritura y el cliente HTTP de las integraciones solo se usa con `get()`.

## 4. Datos sensibles mostrados por error

**Que es.** El riesgo de que, agreguandose un campo "util" (el log del respaldo,
la ruta del archivo, el detalle del ataque), el panel termine publicando
contenido del cliente.

**Como se controla.**

- El esquema no tiene columnas para contenido: `respaldos` no tiene ruta ni
  nombre de archivo.
- `tests/Feature/NoExponeDatosSensibleTest.php` recorre la respuesta JSON y
  falla si aparece una clave cuyo nombre contenga `password`, `token`,
  `secreto`, `correo`, `ip`, `ruta_archivo`, `contenido` u otras.
- Los motivos de error que ve el cliente son genericos; el detalle real queda en
  el registro del servidor, porque la excepcion puede traer URLs internas.

**Riesgo residual:** el filtro de la prueba es por **nombre de clave**. Un
campo malicioso con un nombre inocuo pasaria el filtro. La red real es la
revision, no la prueba.

## 5. La vista `vw_tenants_publicos` no tiene seguridad de fila

**Que es.** Existe una vista que lista las Pymes clientes sin exigir una
empresa de sesión, porque el inicio de sesión la necesita.

**Riesgo.** Cualquiera con acceso directo a la base ve el catalogo de clientes
de Hefesto: nombres, razones sociales y correos de contacto. No es contenido de
clientes, pero es información comercial de Hefesto y de sus clientes.

**Mitigacion actual:** la credencial de la aplicación es la única que tiene la
base, y la vista no se expone en ninguna ruta de la API.

**Pendiente:** cuando exista pagina de acceso propia, conviene reemplazar la
vista por una consulta parametrizada por correo, con indice único, y eliminar
la vista.

## 6. Secretos en el contenedor

**Que es.** Las credenciales de integración llegan por variables de entorno.

**Riesgo.** Cualquiera que pueda ejecutar `docker inspect`, o leer `/proc/1/environ`
en el contenedor, obtiene las claves de los sistemas de Hefesto.

**Mitigacion:** los contenedores corren como usuario sin privilegios
(`app`, `nuxt`), no como root; las bases de datos y Redis **no publican
puertos**.

**Pendiente:** montar los secretos como archivos en vez de variables, o usar el
mecanismo de secretos del orquestador que se use en producción.

## 7. Token de acceso en el navegador

**Que es.** El cliente tiene un token de acceso de larga duracion.

**Como se controla.** El token viaja en una cookie **httpOnly**, la consume
unicamente el servidor de Nuxt en `server/api/panel/[...ruta].ts`, y nunca se
incluye en el HTML ni en el código que se descarga el navegador. El proxy
rechaza cualquier metodo distinto de GET.

**Riesgo residual:** una cookie httpOnly sin `Secure` ni `SameSite` estrictos
puede Viajar en un contexto que no corresponde. Hay que fijar esos atributos en
el proxy de producción (ver `docs/04-INSTALACION.md`).

**Pendiente:** no hay paginas de acceso ni renovacion de token. La vigencia por
defecto son 30 dias y la revocacion es manual. Si un token se filtra, la
respuesta es borrar la fila de `tokens_de_acceso`.

## 8. Sesiones/cache compartidas entre empresas

**Que es.** Redis guarda las consultas cacheadas.

**Como se controla.** La clave de cache incluye la empresa:
`consulta:<tenant_id>:<sistema>`. Hay una prueba dedicada
(`test_la_cache_no_comparte_datos_entre_empresas`).

**Riesgo residual:** si alguien cambia esa clave y saca el `tenant_id`, dos
empresas compartirian la misma entrada, y una veria los datos de la otra. La
prueba protege contra esa regresion, pero no contra un cambio deliberado.

## 9. Superficie de ataque del propio panel

- `GET /api/salud` es público. Devuelve lo minimo (que la app responde y la
  hora) y **no** el estado de los sistemas: permitiria mapear la
  infraestructura de Hefesto sin tener cuenta. Hay una prueba que lo vigila.
- No hay limite de peticiones configurado en este corte. **Pendiente:** agregar
  `throttle` a las rutas del panel.
- El panel tiene un solo rol efectivo (`cliente`). **Pendiente:** definir que
  hace el rol `operador` de Hefesto, si es que lo necesita.

## 10. Lo que este panel NO hace (y por eso el riesgo baja)

- No ejecuta acciones: nada de reinicios, despliegues ni respaldos.
- No muestra contenido: ni correos, ni archivos, ni logs, ni IPs.
- No expone tokens, claves ni variables de entorno en ninguna respuesta.
- No tiene pagina de acceso propia: no hay superficie de robo de contrasenas.

## Resumen de pendientes de seguridad, en orden

1. **Ejecutar las pruebas de RLS sobre PostgreSQL de verdad.** Hoy esa barrera
   no se ha probado. Es el pendiente que mas importa.
2. Definir y probar el comportamiento del rol `operador`, o eliminarlo del
   esquema.
3. Reemplazar `vw_tenants_publicos` por una consulta parametrizada cuando exista
   la pagina de acceso.
4. Anadir limite de peticiones a las rutas del panel.
5. Fijar `Secure`, `HttpOnly` y `SameSite` en la cookie del token, en el proxy.
6. Evaluar secretos como archivos en vez de variables de entorno.
7. Al activar Uptime Kuma, confirmar el esquema de metricas antes de mostrarlo
   a un cliente.

---

## Los dos roles de PostgreSQL

La base del panel corre con **dos** roles, no con uno. La separación es
deliberada: es lo que hace que las políticas de aislamiento pueda alcanzarlos.

### Qué es cada rol

| Rol | Nombre | De dónde sale | Para qué sirve | ¿Lo usa la aplicación? |
|---|---|---|---|---|
| **Arranque** | `panel_operacion_admin` | `POSTGRES_USER` | Crear la base y las tareas administrativas del despliegue | **Nunca** |
| **Aplicación** | `panel_operacion` | `DB_USERNAME`, creado por `deploy/postgres/inicializar-rol.sh` | Todo el tráfico de la API | **Sí, este y solo este** |

Una particularidad del `compose` de esta aplicación: `POSTGRES_DB` no tiene valor
por omisión. Sale de `DB_DATABASE` con `:?falta DB_DATABASE`, así que el despliegue
se detiene si no se declara. No es un descuido: el panel no tiene una base con
nombre fijo.

El rol de arranque no es una convención del estudio: la imagen oficial de
PostgreSQL ejecuta `initdb --username="$POSTGRES_USER"`, así que ese rol nace
**superusuario**.

### Por qué con un solo rol no servía

La documentación de PostgreSQL 18 lo dice de forma textual:

> *«Superusers and roles with the `BYPASSRLS` attribute always bypass the row
> security system when accessing a table. Table owners normally bypass row
> security as well, though a table owner can choose to be subject to row
> security with `ALTER TABLE ... FORCE ROW LEVEL SECURITY`.*

La segunda frase es la clave. `FORCE ROW LEVEL SECURITY` sujeta al **dueño** de
la tabla, pero **no al superusuario**. Mientras la aplicación conectara con el
rol de `POSTGRES_USER`, la migración
`2026_02_01_000008_habilitar_rls_por_tenant.php` habría creado las políticas y
nadie las habría sentido: cada empresa habría visto los agregados de todas las
demás, sin error y sin aviso.

### Los atributos van todos escritos

`deploy/postgres/inicializar-rol.sh`, montado en
`/docker-entrypoint-initdb.d/`, crea el rol con `NOSUPERUSER`, `NOCREATEDB`,
`NOCREATEROLE`, `NOINHERIT` y `NOBYPASSRLS` **explícitos todos**, aunque
`NOBYPASSRLS` ya sea el valor por omisión.

Se escriben igual a propósito: un rol verificado no debería depender de un valor
por omisión que una versión nueva puede cambiar. El script también falla
ruidosamente si falta `POSTGRES_DB`, `APP_DB_ROLE` o `APP_DB_PASSWORD`.

### Por qué el rol de aplicación es dueño de la base

Justo después de crearlo, el script corre:

```sql
ALTER DATABASE :base OWNER TO panel_operacion;
```

donde `:base` es el valor de `DB_DATABASE` en el despliegue, que en este panel
no tiene nombre fijo.

Las migraciones de Laravel se ejecutan con el rol de la aplicación y necesitan
**crear tablas**. La única forma de darles ese permiso sin convertirlos en
superusuario es que ese rol sea el dueño de la base. Desde PostgreSQL 15 el
esquema `public` pertenece al dueño de la base, así que con esto el rol ya
puede crear tablas ahí.

Y este es el punto exacto del diseño: al ser el dueño, **las tablas quedan a su
nombre**, y por eso el `FORCE ROW LEVEL SECURITY` de las tablas con `tenant_id`
sí lo sujeta. Ser dueño no es saltarse la política; es lo que hace que la
política lo alcance. Un superusuario la saltearía siempre.

### Cómo se comprueba, y aquí hay una diferencia con las demás aplicaciones

Existe un comando:

```
php artisan panel:verificar-aislamiento
```

Revisa cinco cosas, en este orden:

1. Que el rol de la conexión no sea superusuario ni tenga `BYPASSRLS`. Lo lee de
   `pg_roles`, por `current_user`, y va **primero**. No depende de que haya datos:
   contra una base recién instalada y vacía lo comprueba igual. Si el rol está
   mal, el comando se detiene ahí y dice qué corregir y dónde está el patrón de
   referencia (`apps/almacen/deploy/postgres/inicializar-rol.sh`).
2. Que las cuatro tablas con `tenant_id` tengan RLS activada y forzada.
3. Que cada una tenga su política, y que la política compare contra
   `public.app_tenant_id()`, con `USING` y con `WITH CHECK`.
4. Que `public.app_tenant_id()` exista y devuelva `null` sin empresa en el
   contexto.
5. Que una consulta sin empresa en el contexto devuelva cero filas.

**La diferencia con las aplicaciones verticales: este comando no escribe nada.**
El panel es de solo lectura —`index.php` rechaza todo lo que no sea GET, HEAD u
OPTIONS, y `routes/api.php` no declara una sola ruta de escritura—, así que no
crea filas temporales como hacen las verticales. Escribir acá significaría meter
filas en tablas de clientes reales y romper el propio contrato del panel.

Esa restricción tiene una consecuencia que el comando resuelve, en vez de
ocultarla. Leer sin empresa en el contexto tiene que dar cero filas, pero ese
cero es ambiguo: puede ser que la tabla esté vacía, o puede ser que la RLS esté
funcionando y ocultando filas que sí existen. Por eso el punto 5 cuenta dos
veces: una sin empresa en el contexto, que tiene que dar cero, y otra dentro del
contexto de una empresa activa de verdad.

- Si la segunda cuenta da filas, la tabla tiene datos y la RLS los está ocultando
  sin empresa. Esa es la evidencia fuerte, y la que en las verticales da la
  lectura cruzada con filas temporales.
- Si la segunda cuenta da cero, la tabla está vacía y el comando lo avisa, porque
  ahí la primera comprobación no prueba nada.

Los puntos 1 a 4 leen el catálogo de PostgreSQL y no dependen de los datos: valen
igual contra una base recién instalada y vacía.

Si se prefiere hacer la comprobación a mano, son las siguientes consultas, con la
misma credencial que usa la aplicación:

```sql
-- 1. El rol de la conexión no puede ser superusuario ni saltar la RLS.
SELECT rolname, rolsuper, rolbypassrls
  FROM pg_roles
 WHERE rolname = current_user;

-- 2. Las tablas con tenant_id tienen RLS activada y forzada.
SELECT c.relname,
       c.relrowsecurity    AS rls,
       c.relforcerowsecurity AS forzada
  FROM pg_class c
  JOIN pg_namespace n ON n.oid = c.relnamespace
 WHERE n.nspname = 'public'
   AND EXISTS (SELECT 1 FROM pg_attribute a
                WHERE a.attrelid = c.oid AND a.attname = 'tenant_id');

-- 3. La función de contexto falla cerrada sin app.tenant_id.
SELECT public.app_tenant_id();
```

La tercera es la que más dice: con la implementación actual devuelve `null`, y
`null` no es igual a ningún `tenant_id`, así que la comparación da falso y la
consulta no devuelve filas. Si devolviera un valor, el aislamiento está abierto.

La tabla `tenants` y la vista `vw_tenants_publicos` quedan fuera a propósito, por
lo que se explica en el punto 5 de este mismo documento.

**Nada de esto se ha ejecutado.** En el equipo donde se escribió este comando no
había PostgreSQL ni Docker, y no hay `php -l` para comprobar la sintaxis. El
código se revisó a mano y no se declara probado. Ver
[10-PRUEBAS-Y-ESTADO.md](10-PRUEBAS-Y-ESTADO.md).