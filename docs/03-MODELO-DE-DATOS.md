# 03 — Modelo de datos

PostgreSQL, porque el panel depende de la **seguridad de fila (RLS)** para el
aislamiento entre empresas. Esa capacidad no existe en SQLite ni en MySQL, y
es la última barrera del requisito de seguridad número uno.

Si se cambia de motor, hay que rehacer el aislamiento. No es un detalle
menor.

## Tablas

### `tenants` — las Pymes clientes

| Columna | Tipo | Nota |
|---|---|---|
| `id` | uuid | Clave primaria |
| `slug` | varchar(48) único | Identificador legible en la URL |
| `nombre` | varchar(160) | Nombre comercial |
| `razon_social` | varchar(160) | Nullable |
| `rut` | varchar(16) | Nullable |
| `correo_contacto` | varchar(190) único | Nullable |
| `activo` | boolean | Default `true` |
| `created_at`, `updated_at` | timestamptz | |

**Excepcion consciente al aislamiento: NO lleva RLS.** Se consulta durante el
inicio de sesión, cuando todavia no existe una empresa de sesión que comparar.

> **Corrección de 18-10-2026.** Este texto y el docblock de `Tenant.php`
> decían que «los datos públicos salen de la vista `vw_tenants_publicos`».
> **Es falso: la vista se crea en la migración y ningún código la consulta.**
> El panel lee la tabla `tenants` a través del modelo Eloquent. Se detectó al
> auditar las 17 aplicaciones del estudio: solo Notaría, Agencia de viajes,
> Estudio de abogados y Control de Obras usan su vista de verdad.
> El riesgo ya estaba bien descrito en `docs/07`, que sí decía la verdad; lo
> que estaba mal era esta afirmación de aquí.

### `usuarios` — who entra al panel

| Columna | Tipo | Nota |
|---|---|---|
| `id` | uuid | |
| `tenant_id` | uuid -> `tenants` | NOT NULL: un usuario siempre pertenece a una empresa |
| `nombre`, `correo` | varchar | `correo` es único global |
| `clave_hash` | varchar | bcrypt; nunca se devuelve |
| `rol` | varchar(32) | `cliente` u `operador` |
| `activo` | boolean | |
| `ultimo_acceso_en` | timestamptz | Nullable |

### `tokens_de_acceso` — como se autentica el panel

| Columna | Tipo | Nota |
|---|---|---|
| `id` | uuid | Es la primera mitad del token |
| `user_id` | uuid -> `usuarios` | |
| `hash_secreto` | varchar(64) único | **SHA-256 del secreto, nunca el token** |
| `caduca_en`, `revocado_en`, `ultimo_uso_en` | timestamptz | |

El token que viaja es `<id>.<secreto>`. Si alguien lee esta tabla, no puede
autenticarse con lo que encuentra.

### `sitios` — catalogo de sitios del cliente

| Columna | Tipo | Nota |
|---|---|---|
| `tenant_id` | uuid | Con RLS |
| `nombre` | varchar(120) | |
| `host` | varchar(190) | Sin esquema ni ruta. Único junto a `tenant_id` |
| `activo`, `notas` | | |

**Esto no es el monitoreo.** Es el catalogo que mantiene Hefesto. Los datos de
disponibilidad vienen de las integraciones; este registro dice a que sitio
pertenece cada dato.

### `respaldos` — historial, solo indicadores

| Columna | Tipo | Nota |
|---|---|---|
| `tenant_id` | uuid | Con RLS |
| `origen` | varchar(60) | Que sistema produjo el registro |
| `iniciado_en`, `terminado_en` | timestamptz | |
| `estado` | varchar(24) | `exitoso`, `fallido`, `en_proceso`, `interrumpido` |
| `verificacion` | varchar(24) | `verificado`, `fallido`, `pendiente`, `no_aplica` |
| `verificado_en` | timestamptz | Nullable |
| `archivos`, `bytes` | bigint | Nullable. Contadores, no contenido |
| `duracion_segundos` | int | Nullable |

**Lo que NO existe y por que:** no hay columna para ruta, nombre de archivo,
checksum por archivo, usuario ni contenido. No es una omision pendiente: el
panel no debe poder mostrar el contenido de los datos del cliente ni siquiera
por accidente. Agregar una columna así despues seria facil; por eso la prueba
`tests/Feature/NoExponeDatosSensiblesTest.php` vigila las claves de la respuesta.

### `contadores_de_seguridad` — cantidades por dia

| Columna | Tipo | Nota |
|---|---|---|
| `tenant_id`, `dia` | uuid, date | Unicos juntos. Con RLS |
| `detectados`, `bloqueados` | int | Cantidades |
| `por_tipo` | jsonb | Mapa tipo -> cantidad |

Sin direccion IP, sin agente de usuario, sin ruta atacada y sin carga util. Una
IP identifica a una persona: es dato personal y el panel no lo expone.

### `estados_de_integracion` — memoria del panel

| Columna | Tipo | Nota |
|---|---|---|
| `tenant_id`, `sistema` | uuid, varchar(32) | Unicos juntos. Con RLS |
| `estado` | varchar(24) | Último estado conocido |
| `motivo` | varchar(255) | **Siempre generico**, nunca la respuesta cruda |
| `consultado_en`, `ultimo_exito_en` | timestamptz | |
| `intentos` | int | |

Sirve para que el cliente vea "Kopia: no disponible desde las 14:20" en vez de
un cero.

## Seguridad de fila (RLS)

Migración: `2026_02_01_000008_habilitar_rls_por_tenant.php`.

```sql
CREATE OR REPLACE FUNCTION public.app_tenant_id() RETURNS uuid
LANGUAGE sql STABLE AS $$
    SELECT NULLIF(current_setting('app.tenant_id', true), '')::uuid
$$;

ALTER TABLE sitios ENABLE ROW LEVEL SECURITY;
ALTER TABLE sitios FORCE ROW LEVEL SECURITY;
CREATE POLICY sitios_politica_tenant ON sitios
    USING (tenant_id = public.app_tenant_id())
    WITH CHECK (tenant_id = public.app_tenant_id());
```

Se aplica a `sitios`, `respaldos`, `contadores_de_seguridad` y
`estados_de_integracion`.

Dos detalles que importan:

- **`FORCE ROW LEVEL SECURITY`**: sin esto, el dueño de la tabla no estaria
  sujeto a las políticas.
- **`app_tenant_id()` devuelve `NULL` cuando la variable no esta fijada**, y
  `tenant_id = NULL` es falso. Es decir: si el contexto no se fijo, la
  consulta devuelve **cero filas**. Falla cerrando.

La variable la escribe `ContextoTenant::establecer()` con
`set_config(..., true)`, es decir **con alcance de transacción**. Con un `SET`
normal (ambito de sesión) el valor sobreviviria, y en un worker de colas que
reutiliza la conexión para peticiones de empresas distintas, una peticion
podria leer datos de otra PyME.

## Indices

| Tabla | Indice | Por que |
|---|---|---|
| `usuarios` | `(tenant_id, activo)` | Listado de usuarios por empresa |
| `sitios` | `(tenant_id, activo)` | Modulo Sitios |
| `sitios` | único `(tenant_id, host)` | No duplicar el mismo sitio |
| `respaldos` | `(tenant_id, iniciado_en)` | Historial ordenado del mas nuevo |
| `contadores_de_seguridad` | único `(tenant_id, dia)` | Un contador por dia |
| `estados_de_integracion` | único `(tenant_id, sistema)` | Una fila por sistema y empresa |

## Lo que falta para producción

- **No hay migraciones de indices con `CONCURRENTLY`** para una tabla grande:
  hoy son tablas pequenas, pero conviene revisarlo antes de crecer.
- **No hay política de retencion**: la tabla `respaldos` crece sin limite.
  Ver `docs/08-RESPALDOS.md`.
- **No hay versionado de esquema**: no hay herramienta que registre el
  historial de cambios del esquema entre versiones del panel.
- **La vista `vw_tenants_publicos` no tiene seguridad de fila.** Es
  intencional (existe para el inicio de sesión), pero significa que un acceso
  directo a la base muestra el catalogo de Pymes. Ver `docs/07`.
- **La migración de RLS se omite en SQLite.** Las pruebas que dependen de RLS se
  saltan solas en ese motor, y quedan declaradas como pendientes en `docs/10`.