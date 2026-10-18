# 08 — Respaldos

Este documento tiene dos partes que no hay que mezclar:

1. **Que respalda el panel y como se restaura.** De esto si se puede hablar con
   precisión.
2. **El historial de respaldos que el panel MUESTRA al cliente.** Eso viene de
   otra parte y hay que entenderlo bien, porque es donde esta el riesgo de
   mostrar ceros falsos.

## Parte 1 — Respaldar el panel

### Que es persistente

| Volumen | Contenido | Se puede recuperar |
|---|---|---|
| `postgres_datos` | Base del panel: empresas, usuarios, sitios, historial de respaldos, contadores | **Si, es lo critico** |
| `redis_datos` | Cache de consultas | **No.** Es recreable: se regenera sola al primer uso |

**Lo que NO se respalda:**

- El código del panel: esta en el repositorio y se despliega de nuevo.
- `.env`: vive en el gestor de secretos del despliegue. Si se necesita una
  copia, la tiene el gestor, no el servidor.
- El volumen de Redis: es cache. Perderlo significa esperar un TTL, nada mas.

### Como obtener una copia coherente

`pg_dump` con la base en caliente es **consistente**: PostgreSQL toma una
instantanea y no bloquea las escrituras del panel. Y como este panel es de solo
lectura y casi no escribe, la ventana es despreciable.

```bash
docker compose -f deploy/compose.produccion.yaml exec -T postgres \
  pg_dump -U panel_operacion -Fc panel_operacion > panel_$(date +%F).dump
```

### Manifiesto y checksums

Cada copia se verifica al crearla. **El respaldo se marca completo solo al
terminar bien**: si el `pg_dump` falla a mitad, el archivo esta incompleto y hay
que borrarlo, no archivarlo.

```bash
# Calcular el checksum
sha256sum panel_$(date +%F).dump > panel_$(date +%F).dump.sha256

# Verificarlo antes de confiar en el
sha256sum -c panel_$(date +%F).dump.sha256
```

Un checksum que nunca se verifico no es evidencia de nada.

### Donde dejar las copias

**Aun no esta decidido, y esa es una decisión pendiente, no un detalle.** La
tabla de respaldos que muestra el panel **no guarda** la ruta de ninguna copia:
guarda fecha, estado, cantidad de archivos y si fue verificada. Que es
justamente el indicador agregado que el cliente necesita.

Antes de decidir, hay que responder: donde viven las copias, quien las borra, y
que pasa con el cifrado. **Ese punto queda abierto a propósito.**

### Restauración

1. **Dejar el panel detenido.** No se restaura con el servicio en pie.
2. Verificar el checksum del archivo.
3. Comprobar que el destino esta vacio. Si hay datos, la restauración se
   niega salvo que se pase un `--force` explicito. Restaurar encima de una base
   con datos puede dejar un estado incoherente que es peor que no restaurar.
4. Restaurar:

   ```bash
   docker compose -f deploy/compose.produccion.yaml exec -T postgres \
     pg_restore -U panel_operacion -d panel_operacion --clean --if-exists < panel_2026-02-10.dump
   ```

5. **Dejar el servicio detenido despues de restaurar**, hasta que alguien revise
   el estado.

**Nunca se hace `DROP` ni se sobrescribe automáticamente.** Una restauración
automática que corre sin supervision es como se pierde una operación un viernes
a las seis de la tarde.

## Parte 2 — El historial que ve el cliente

### De donde sale

De la tabla `respaldos`, que es **memoria del panel**, no lectura en vivo del
sistema de respaldo. La integración con Kopia esta desactivada porque no se
pudo verificar una credencial de solo lectura (ver `docs/02-INTEGRACIONES.md`).

Mientras tanto, esa tabla la alimenta Hefesto por un proceso fuera de banda,
que **todavia no esta escrito** y queda como pendiente declarado.

### Por que esto importa mas de lo que parece

El modulo Respaldos responde a la pregunta que mas importa para un cliente:
"esta mi información a salvo?". Hay una sola forma de responder mal con este
modulo, y es devolver **ceros falsos**:

- Si no hay registros, la API responde `sin_registros: true` y
  `ultimo_respaldo: null`. **No** devuelve un objeto vacio con estado
  "exitoso".
- Si el último respaldo salio con errores, se muestra "Con errores", aunque el
  sistema de respaldo diga lo que quiera en su consola.

Hay una prueba dedicada a esto:
`tests/Feature/DegradacionEleganteTest::test_sin_datos_de_respaldo_el_panel_lo_dice_y_no_inventa`.

### Que se guarda y que no

**Se guarda:** fecha de inicio y fin, estado, estado de verificación, cantidad
de archivos, bytes totales, duracion.

**No se guarda, y no existe columna para ello:** ruta del respaldo, nombres de
archivo, checksum por archivo, usuario, contenido.

El panel no puede mostrar el contenido de un respaldo porque **no hay por donde
entrar**. Agregar despues una columna "ruta" seria facil, y por eso
`tests/Feature/NoExponeDatosSensiblesTest.php` vigila que la respuesta no
contenga claves como `ruta_archivo` o `contenido`.

### Verificación: cuatro estados, no dos

| Estado | Significado |
|---|---|
| `verificado` | El contenido se verifico y esta bien |
| `fallido` | La verificación fallo. **No es lo mismo que "sin verificar"** |
| `pendiente` | Nadie lo verifico todavia |
| `no_aplica` | El sistema no tiene verificación configurada |

La distincion entre `fallido` y `pendiente` es la que evita el malentendido
clasico: un respaldo sin verificar no es un respaldo que haya dado bien.

## Pendientes de este documento

1. **Definir el destino, la retencion y el cifrado de las copias.**
2. **Escribir el proceso que alimenta la tabla `respaldos`**, o la integración
   con Kopia cuando se verifique su credencial de solo lectura.
3. **Definir una política de retencion para la tabla `respaldos`**: hoy crece sin
   limite. Una política simple es conservar el detalle de 90 dias y el conteo
   mensual por un ano.
4. **Revisar el volumen de la base**: si la tabla `respaldos` llega a millones de
   filas, conviene particionarla por mes.