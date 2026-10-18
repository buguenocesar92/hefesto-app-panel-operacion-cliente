# Panel de Operación del Cliente

**Hefesto Studio** — tablero donde una PyME cliente ve, en un solo lugar, el
estado de los sistemas que Hefesto le administra.

> **Estado: código escrito, sin ejecutar.**
> En el entorno donde se construyo este panel no habia Docker, ni PHP, ni
> Composer, ni servidores externos. Nada de lo que hay aquí se ha levantado ni
> se ha conectado a un sistema real. Que es exactamente lo que hace falta que
> sea este tablero: no se presento nada como funcionando sin haberlo probado.
> El detalle esta en [`docs/10-PRUEBAS-Y-ESTADO.md`](docs/10-PRUEBAS-Y-ESTADO.md).

---

## Que problema resuelve

Hefesto vende sus productos como servicio VPS administrado y ya tiene catorce
kits de despliegue listos. El cliente paga porque eso funcione, pero hoy no ve
nada: si un respaldo fallo o el sitio esta lento, se entera porque algo se
rompio.

Este panel convierte la operación invisible en algo demostrable. El cliente
deja de preguntarse "que estan haciendo con mi servidor" y pasa a tener
evidencia. Eso sostiene la renovacion.

## Que es y que no es

**Es** software original escrito por Hefesto: una aplicación propia, multi
cliente, de solo lectura.

**No es** un kit de despliegue de una herramienta de terceros. No reempaca
Uptime Kuma ni SigNoz ni Coolify: los consulta, y solo con credenciales de
lectura.

**No es** un panel de control. No reinicia nada, no dispara respaldos, no
modifica nada. Cuando el cliente necesita una accion, el panel se lo dice y lo
deriva a Hefesto.

## Las tres reglas que gobiernan todo

1. **Solo lectura.** No existe un solo metodo HTTP de escritura en la API, y
   hay dos capas que rechazan cualquier otro (`api/public/index.php` y el
   middleware `ResolverTenant`).
2. **Nunca contenido sensible.** Ni correos, ni respaldos, ni tokens, ni
   variables de entorno, ni claves. Solo indicadores agregados: "último
   respaldo: hace 6 horas, verificado, 1.240 archivos".
3. **Un cliente solo ve lo suyo.** Aislamiento por empresa en la aplicación
   (alcance global) y en la base de datos (seguridad de fila de PostgreSQL),
   con una prueba dedicada que intenta la fuga y falla.

Los riesgos completos estan en
[`docs/07-SEGURIDAD-Y-AISLAMIENTO.md`](docs/07-SEGURIDAD-Y-AISLAMIENTO.md).

## Estado de las integraciones

| Sistema | Estado | Motivo |
|---|---|---|
| Uptime Kuma | Cliente implementado, **desactivado** | Endpoint y credencial verificados; falta confirmar el esquema de metricas contra una instancia real |
| SigNoz | **Desactivado** | No se pudo verificar endpoint de lectura ni credencial de solo lectura |
| Kopia | **Desactivado** | La única credencial conocida permite escribir respaldos |
| Coolify | **Desactivado** | No se pudo verificar que sus tokens permitan ambito de solo lectura |
| CrowdSec | **Desactivado** | La API local esta verificada; faltan rutas exactas y credencial acotada |
| Authentik | **Desactivado** | No se pudo verificar una credencial con ambito de solo lectura |

Un sistema sin integración verificada queda **apagado y documentado**, nunca
simulado. Una tarjeta que dice "desactivada" es util; una tarjeta con ceros
falsos hace que el cliente crea que su respaldo funciono. El detalle y las
fuentes estan en [`docs/02-INTEGRACIONES.md`](docs/02-INTEGRACIONES.md).

## Stack

- **Backend:** Laravel con PostgreSQL.
- **Frontend:** Nuxt con SSR.
- **Cache:** Redis, con TTL por sistema y la hora de la última actualización
  siempre visible en pantalla.
- **Despliegue:** Docker Compose con versiones fijadas.

## Estructura

```
panel-operacion-cliente/
├── README.md
├── .env.example          # Plantilla, sin ningun secreto real
├── docs/                 # 01 a 10
├── api/                  # Laravel: codigo, migraciones, semilla, pruebas
├── web/                  # Nuxt con SSR
└── deploy/               # Compose de desarrollo y produccion, Dockerfiles
```

## Documentacion

| Documento | Que resuelve |
|---|---|
| [01 Arquitectura](docs/01-ARQUITECTURA.md) | Como se arma el panel y por que así |
| [02 Integraciones](docs/02-INTEGRACIONES.md) | Cada sistema, que se verifico y que falta |
| [03 Modelo de datos](docs/03-MODELO-DE-DATOS.md) | Tablas y por que son esas |
| [04 Instalación](docs/04-INSTALACION.md) | Levantar el stack |
| [05 Configuración](docs/05-CONFIGURACION.md) | Variables, TTL, por que no hay facturacion |
| [06 Primer cliente](docs/06-PRIMER-CLIENTE.md) | Dar de alta la primera PyME |
| [07 Seguridad y aislamiento](docs/07-SEGURIDAD-Y-AISLAMIENTO.md) | Riesgos, con frases claras |
| [08 Respaldos](docs/08-RESPALDOS.md) | Que se respalda y como se restaura |
| [09 API](docs/09-API.md) | Endpoints y ejemplos |
| [10 Pruebas y estado](docs/10-PRUEBAS-Y-ESTADO.md) | Que se ejecuto y que no |

## Inicio rapido

Requiere Docker y Docker Compose.

```bash
cp .env.example .env
# Completar APP_KEY, DB_PASSWORD y REDIS_PASSWORD (ver .env.example)

docker compose -f deploy/compose.desarrollo.yaml --env-file .env up -d
docker compose -f deploy/compose.desarrollo.yaml exec api php artisan migrate --seed
```

El panel queda en `http://127.0.0.1:3000`. La API responde en
`http://127.0.0.1:8000/api/salud`.

**Nota:** los comandos de arriba estan escritos y son los que se seguirian, pero
**no se han ejecutado**. Ver `docs/04-INSTALACION.md`.

## Licencia

Software propietario de Hefesto Studio. Nuxt es MIT; Laravel es MIT.