# 02 — Integraciones

Este es el documento mas importante del panel. Cada sistema que Hefesto
administra necesita una **credencial de solo lectura** para poder mostrar su
estado. Si ese sistema solo ofrece un token de administrador, **la integración
no se activa**: se documenta y queda apagada.

Se prefiere una tarjeta "desactivada" a una integración que expone el sistema
entero. El motivo es concreto: una credencial demasiado amplia no muestra solo
la disponibilidad de un sitio, muestra también la configuración del servidor,
los nombres de los contenedores y, en el peor caso, los secretos del backup.

## Resumen

| Sistema | Cliente | Estado por defecto | Verificación |
|---|---|---|---|
| Uptime Kuma | Si | **Desactivado** | Parcial: endpoint y credencial verificados |
| SigNoz | No | Desactivado | Sin verificar |
| Kopia | No | Desactivado | Sin verificar |
| Coolify | No | Desactivado | Sin verificar |
| CrowdSec | No | Desactivado | Parcial: la API local existe |
| Authentik | No | Desactivado | Sin verificar |

Que "el cliente no exista" significa que no hay código de consulta. No es un
simbolo pendiente que devuelva ceros: `IntegracionDesactivada` devuelve estado
"desactivada" con su motivo, y la tarjeta del cliente lo muestra.

---

## Uptime Kuma — cliente implementado, desactivado

### Que se verifico

Fuente: <https://github.com/louislam/uptime-kuma/wiki/Prometheus-API-Keys>
(leida directamente; la seccion exige Uptime Kuma 1.21 o superior).

- El endpoint de metricas es **`/metrics`**.
- Se protege con autenticación básica y la clave se pasa en la cabecera
  `Authorization`. El ejemplo oficial es `curl -u":<clave>"`, es decir, **el
  usuario va vacio** y la clave es la contrasena.
- **"The API key cannot be used to access the web interface"**: la clave no
  abre la interfaz web. Solo permite leer metricas. Esta es la razon por la que
  esta integración puede existir sin exponer el sistema.
- Al crear la primera clave, la autenticación básica del endpoint queda
  permanentemente deshabilitada.

### Que NO se verifico

Los **nombres y las etiquetas de las metricas** que pública Uptime Kuma no
estan documentados en esa fuente. No se pudo leer el archivo que las define en
el repositorio del proyecto.

Por eso el panel **no decide que significa ningun valor**. `SitiosService`
toma las series que llegan y busca, entre sus etiquetas, la que coincida con el
host del sitio registrado por Hefesto. No hay una sola constante del tipo
`monitor_status = 1 significa arriba`, porque eso seria inventar.

### Por que queda desactivada

Porque el cruce "etiqueta -> sitio" hay que confirmarlo contra una instancia
real antes de mostrarlo a un cliente. El código esta escrito y probado en su
parte de formato (`tests/Unit/LectorPrometheusTest.php`), pero la
interpretacion queda pendiente.

### Como activarla

1. En Uptime Kuma: **Settings → API Keys → Generate**. Anotar la clave (solo se
   muestra una vez).
2. En el gestor de secretos del despliegue:
   `UPTIME_KUMA_URL`, `UPTIME_KUMA_CLAVE_API`.
3. `UPTIME_KUMA_HABILITADA=true`.
4. `PANEL_INTEGRACIONES_ACTIVAS=uptime_kuma`.
5. Confirmar contra una instancia real que las etiquetas traen el host del
   sitio, y documentar el hallazgo en este mismo documento.

---

## SigNoz — desactivado

**Estado de la verificación: ninguna.**

No se pudo confirmar en documentacion oficial un endpoint de lectura de
metricas con una credencial de solo lectura. Las rutas consultadas no
entregaron la documentacion (secciones inexistentes o paginas generadas del
sitio sin contenido recuperable).

**Por que no se activa con un token comun:** mientras no se verifique que el
token que emite SigNoz tenga ambito de lectura, usarlo seria exactamente el
riesgo que este panel evita: una credencial que abre el sistema entero.

**Pendiente:** confirmar (a) el endpoint de lectura y (b) si existe una
credencial con ambito de solo lectura. Con ambas respuestas, se escribe el
cliente.

---

## Kopia — desactivado

**Estado de la verificación: ninguna sobre el modelo de credenciales.**

La documentacion del servidor de Kopia no se pudo leer en este entorno. Lo que
se sabe por conocimiento previo — y que por eso **no se da por verificado** —
es que la API del servidor autentica con una cuenta de servidor, y que esa
cuenta también puede iniciar y borrar respaldos.

**Por que no se activa:** una cuenta que puede borrar respaldos no es una
credencial de solo lectura, aunque solo la usemos para leer. El panel no la
va a pedir.

**Pendiente:** verificar si existe un rol o usuario restringido a lectura en el
servidor de Kopia. Si existe, se implementa el cliente. Si no existe, la
integración queda descartada de forma permanente y habra que decirselo al
cliente con claridad: "no podemos mostrarle su respaldo porque el sistema no
tiene una forma de mostrarlo sin abrirse en canal".

**Mientras tanto**, el historial de respaldos que el panel muestra proviene de
la tabla `respaldos`, que Hefesto alimenta fuera de banda (ver
`docs/08-RESPALDOS.md`). Los datos siguen siendo ciertos; lo que no existe es
la lectura en vivo del sistema.

---

## Coolify — desactivado

**Estado de la verificación: ninguna.**

No se pudo confirmar que los tokens de la API de Coolify permitan limitar el
ambito a solo lectura. Sin esa confirmacion, usarlos equivaldria a guardar una
credencial de administración de toda la plataforma de despliegue.

**Pendiente:** verificar si la API admite ambito por token. Si lo admite, se
implementa el cliente con el ambito minimo.

---

## CrowdSec — desactivado

**Estado de la verificación: parcial.**

**Verificado.** Fuente:
<https://github.com/crowdsecurity/crowdsec/blob/master/pkg/apiserver/apiserver.go>
(leido directamente). Ese archivo construye el servidor local (LAPI) y deja
constancia de:

- Existe una API HTTP local con rutas bajo `/v1`.
- Se autentica con middlewares propios, con variantes para agentes y para
  bouncers, y con soporte de autenticación por TLS.
- El servidor escucha por TCP o por socket Unix, y tiene configuración propia
  de confianza de IP.

**No verificado.** No se pudieron confirmar:

1. Las rutas exactas de lectura que usaria el panel.
2. Si la configuración admite una credencial con **rutas permitidas**, es decir,
   una clave que solo pueda consultar decisiones y no pueda borrarlas ni
   agregarlas.

**Por que no se activa:** el panel necesita una credencial que solo lea. Saber
que la API existe no basta: si la clave permite `DELETE /v1/decisions`, es una
credencial de escritura con otro nombre.

**Pendiente:** verificar las rutas de lectura y la configuración de credencial
acotada. Con las dos, el cliente es pequeno: la API entrega la cantidad de
decisiones y la de bloqueos, que es exactamente lo que muestra el modulo de
Seguridad.

---

## Authentik — desactivado

**Estado de la verificación: ninguna.**

No se pudo confirmar que exista una credencial con ambito de solo lectura.
Authentik permite crear usuarios y roles, pero no se verifico que un rol
cualquiera sirva para leer el estado del servicio sin poder modificar usuarios.

**Por que no se activa:** un token de administrador de Authentik da acceso a
crear y borrar usuarios de identidad. No se usa.

**Pendiente:** verificar si un rol restringido puede leer el estado del servicio
y contar usuarios activos sin permisos de escritura.

---

## Como se decide el estado de una integración

`config/integraciones.php` declara, por sistema:

- `habilitada`: si el operador la enciende.
- `verificacion.estado`: `verificado`, `parcial` o `no_verificada`.
- `verificacion.fuente`: la URL de donde salio la evidencia.
- `verificacion.pendiente`: que falta para poder activarla.

Y `RegistroIntegraciones` arma el catalogo en tiempo de ejecución:

- Si el sistema no esta en `PANEL_INTEGRACIONES_ACTIVAS`, queda **desactivado**.
- Si esta en la lista pero `habilitada` es `false`, queda **desactivado**.
- Si esta habilitado pero le falta URL o clave, queda **sin configurar**.
- Si es el único sistema con cliente verificado, se construye el cliente.
- Cualquier sistema futuro cae en "todavia no hay un cliente verificado".

Esa última rama es deliberada: prefiero que un sistema nuevo caiga en
"desactivado" a que caiga en un `default` que consulte algo sin verificarlo.

## Que se necesita para activar cada una

| Sistema | Falta |
|---|---|
| Uptime Kuma | Confirmar el cruce etiqueta -> sitio en una instancia real |
| SigNoz | Endpoint de lectura + credencial de solo lectura |
| Kopia | Confirmar si existe un rol restringido a lectura |
| Coolify | Confirmar si el token admite ambito de solo lectura |
| CrowdSec | Rutas de lectura + credencial con rutas permitidas |
| Authentik | Confirmar si un rol restringido alcanza para leer el estado |

Cuando se verifique uno, se escribe el cliente, se agrega su prueba y se
actualiza esta tabla. El orden recomendado es **Kopia** (es el dato que mas le
importa al cliente) y **CrowdSec** (es el que mas cerca esta de estar listo).