# 01 — Arquitectura

> PostgreSQL corre con dos roles: uno de arranque y otro de aplicación. Solo el
> de aplicación conecta con el sistema, y es el que las políticas de
> aislamiento pueden alcanzar. Ver [07](07-SEGURIDAD-Y-AISLAMIENTO.md).

## La idea en una frase

Un tablero de **una sola direccion**: el panel consulta a los sistemas que
Hefesto administra y muestra el resultado. Nunca les pide nada.

## Diagrama

```
   Navegador del cliente
          |
          |  (SSR: la peticion se resuelve en el servidor de Nuxt)
          v
  ┌───────────────────┐        El token vive en una cookie httpOnly
  │  Nuxt (SSR)       │        y solo se usa en el servidor.
  └─────────┬─────────┘
            |  GET, con Authorization: Bearer <token>
            v
  ┌───────────────────┐
  │  API Laravel      │  1. Autentica el token (SHA-256 en la base)
  │                   │  2. Resuelve la empresa DESDE EL USUARIO
  │                   │  3. Sirve desde cache de Redis
  └────┬─────────┬────┘
       │         │
       v         v
  ┌─────────┐  ┌──────────────────────┐
  │PostgreSQL│  │ Redis (cache)        │
  │ + RLS   │  │ TTL por sistema      │
  └─────────┘  └──────────┬───────────┘
                          │  TTL vencido
                          v
              ┌───────────────────────────────┐
              │  Sistemas de Hefesto         │
              │  Uptime Kuma · SigNoz ·      │
              │  Kopia · Coolify ·           │
              │  CrowdSec · Authentik        │
              └───────────────────────────────┘
                  SOLO GET. Credenciales de solo lectura.
```

## Las cuatro decisiones que explican el diseno

### 1. El cache va en el servidor, no en el navegador

Cada recarga de pantalla consultaria siete sistemas externos. Con Redis y un
TTL por sistema, recargar cinco veces en un minuto no genera cinco consultas.

La consecuenciaobligatoria: el panel **siempre muestra de cuando son los
datos**. Si la cache supera un umbral, la tarjeta pasa a "datos antiguos",
aunque el contenido siga siendo real. Sin eso, el cliente tomaria un dato de
hace diez minutos por uno en vivo, y un tablero que miente es peor que uno
que no existe.

### 2. La degradacion se diseña antes que el camino feliz

Cada sistema se consulta por separado y cada tarjeta se resuelve por separado.
Si CrowdSec no responde, su tarjeta dice "no disponible" y las otras cinco se
pintan igual. El mensaje que ve el cliente es generico; el detalle real queda
en el registro del servidor, porque la excepcion puede traer URLs internas.

### 3. El aislamiento se enforce en dos capas

- **Aplicación:** un alcance global de Eloquent agrega `where tenant_id = ?` a
  toda consulta de un modelo con empresa. Si no hay empresa resuelta, lanza una
  excepcion en vez de devolver filas. Falla cerrado.
- **Base de datos:** políticas de seguridad de fila (RLS) de PostgreSQL sobre
  las cuatro tablas con datos de clientes. Siguen valiendo aunque alguien
  escriba SQL a mano con la credencial de la aplicación.

La empresa se resuelve **siempre** desde el usuario autenticado. Nunca desde un
parámetro de la ruta, del cuerpo ni de una cabecera.

### 4. "No disponible" y "desactivada" son estados distintos

- **No disponible:** la integración existe, el sistema no respondio. Es una
  falla que Hefesto tiene que mirar.
- **Desactivada:** la integración no existe, porque no se pudo verificar una
  credencial de solo lectura. Es una decisión, documentada.

Mezclarlos seria mentir en una de las dos direcciones.

## Estructura del código

```
api/
├── app/
│   ├── Console/Commands/        RefrescarCacheCommand (la unica tarea)
│   ├── Enums/                   Sistema, EstadoIntegracion, EstadoRespaldo, EstadoVerificacion
│   ├── Http/
│   │   ├── Controllers/Api/     SaludController + Api/Panel/* (un controlador por modulo)
│   │   └── Middleware/          AutenticarConToken, ResolverTenant
│   ├── Models/                  Tenant, User, Site, BackupRun, SecurityCounter, IntegrationState
│   │   └── Concerns/            PerteneceATenant (el alcance global)
│   └── Services/
│       ├── Cache/               ConsultaCacheada (TTL, marca de tiempo, degradacion)
│       ├── Integraciones/       Contrato, catalogo y el unico cliente verificado
│       │   └── UptimeKuma/
│       ├── Panel/               Un servicio por modulo
│       └── Tenancy/             ContextoTenant (fija y limpia la empresa)
├── config/
│   ├── integraciones.php        Estado, credenciales y evidencia de cada sistema
│   └── panel.php                TTL por sistema
├── database/
│   ├── migrations/              8 migraciones, la ultima habilita RLS
│   └── seeders/                 Dos Pymes de ejemplo (la segunda prueba el aislamiento)
└── tests/
    ├── Feature/AISlamiento/     La prueba maestra de aislamiento
    ├── Feature/                 Degradacion, solo lectura, datos sensibles
    └── Unit/                    Cache, lector de metricas, catalogo

web/
├── app/
│   ├── components/              TarjetaIntegracion, IndicadorDeEstado
│   ├── composables/             usePanel (tipos minimos), tiempoDesde
│   ├── layouts/                 Navegacion y aviso de solo lectura
│   └── pages/                   Un archivo por modulo
├── server/api/panel/[...ruta].ts Proxy a la API; el token no sale del servidor
└── nuxt.config.ts               SSR activado
```

## El recorrido de una peticion

1. El navegador pide `/` a Nuxt. Nuxt resuelve la pagina en el servidor.
2. La pagina pide sus datos a `/api/panel/resumen` (Nuxt, no el navegador).
3. El servidor de Nuxt lee la cookie `panel_token` y llama a la API de Laravel
   con `Authorization: Bearer`. Si el token no esta, responde 401.
4. Laravel valida el token comparando SHA-256, fija la empresa desde el usuario
   y resuelve cada tarjeta de sistema por separado.
5. Las que estan en cache se devuelven con su marca de tiempo; las vencidas se
   consultan y se cachean de nuevo.
6. Si una consulta falla, esa tarjeta vuelve como "no disponible" y el resto
   sigue.
7. Nuxt renderiza el HTML completo. El token nunca llega al navegador.

## Adonde NO llega este primer corte

- Sin pagina de acceso: Hefesto emite el token y el cliente lo recibe por un
  canal controlado. Ver `docs/06-PRIMER-CLIENTE.md`.
- Sin colas: la única tarea programada refresca la cache cada diez minutos.
- Sin notificaciones: el panel informa, no avisa.
- Con una sola integración activa. Ver `docs/02-INTEGRACIONES.md`.