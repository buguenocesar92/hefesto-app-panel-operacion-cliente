# 06 — Primer cliente

Dar de alta la primera PyME en el panel.

> **Estado: este procedimiento esta escrito, no ejecutado.** Ver
> `docs/10-PRUEBAS-Y-ESTADO.md`.

## 1. Crear la empresa

No hay interfaz de alta: la crea Hefesto, con una consola artisan o un comando
dedicado. Por que no hay pantalla de alta:

- El alta de un cliente es una operación de escritura, y este panel es de solo
  lectura.
- Quien da de alta a un cliente decide que datos ve. Eso es una decisión de
  Hefesto, no del sistema.

Crear la empresa y su primer usuario con Tinker:

```bash
docker compose -f deploy/compose.produccion.yaml exec api php artisan tinker
```

```php
$empresa = App\Models\Tenant::create([
    'slug' => 'panaderia-los-volcanes',
    'nombre' => 'Panaderia Los Volcanes',
    'razon_social' => 'Panaderia Los Volcanes SpA',
    'rut' => '76.123.456-7',
    'correo_contacto' => 'contacto@panaderialosvolcanes.cl',
    'activo' => true,
]);

$usuario = App\Models\User::create([
    'tenant_id' => $empresa->id,
    'nombre' => 'Nombre del contacto',
    'correo' => 'nombre@panaderialosvolcanes.cl',
    'clave_hash' => bcrypt('contrasena-fuerte-y-unica'),
    'rol' => 'cliente',
    'activo' => true,
]);
```

## 2. Registrar los sitios

Los sitios los mantiene Hefesto. Son el catalogo que permite cruzar las
metricas del sistema de monitoreo con lo que el cliente realmente tiene.

```php
App\Models\Site::create([
    'tenant_id' => $empresa->id,
    'nombre' => 'Tienda en linea',
    'host' => 'tienda.panaderialosvolcanes.cl',
    'activo' => true,
]);
```

**El `host` va sin esquema y sin ruta**: `tienda.panaderialosvolcanes.cl`, no
`https://tienda.panaderialosvolcanes.cl/carrito`. Es porque el cruce con las
etiquetas de las metricas es exacto.

**Verificar el aislamiento antes de seguir.** Con la empresa creada y al menos
un sitio, hay que confirmar que no aparecen datos de otra empresa:

```bash
docker compose -f deploy/compose.produccion.yaml exec -e DB_CONNECTION=pgsql \
  api php artisan test --testsuite=Aislamiento
```

## 3. Emitir el token de acceso

```php
$secreto = bin2hex(random_bytes(32));

$token = App\Models\PersonalAccessToken::create([
    'user_id' => $usuario->id,
    'nombre' => 'panel-web',
    'hash_secreto' => hash('sha256', $secreto),
    'caduca_en' => now()->addDays((int) config('auth.vigencia_token_dias')),
]);

// Este es el token. Se muestra UNA sola vez.
echo $token->id.'.'.$secreto, PHP_EOL;
```

El token tiene la forma `<id>.<secreto>`. En la base solo queda el SHA-256 del
secreto.

**Como se lo entrega Hefesto al cliente.** El panel no tiene pagina de acceso
en este primer corte, y esa es una decisión consciente: una pantalla de acceso
es superficie de robo de contrasenas, y el cliente ya tiene usuarios en
Authentik (o deberia tenerlos). El camino previsto:

1. Hefesto emite el token.
2. Se lo entrega al cliente por un canal controlado.
3. El cliente lo pega en el navegador, y el frontend lo guarda en la cookie
   `panel_token` (httpOnly).

**Pendiente para producción:** reemplazar este paso por un inicio de sesión
contra Authentik, una vez que la integración de Authentik este verificada y se
confirme que sirve para autenticar sin abrir permisos de escritura.

## 4. Activar integraciones (opcional, con criterio)

Solo despues de leer `docs/02-INTEGRACIONES.md`. Recordatorio: **en este corte
solo Uptime Kuma tiene cliente, y esta desactivado**. Activarlo exige confirmar
el cruce de etiquetas contra una instancia real.

```bash
PANEL_INTEGRACIONES_ACTIVAS=uptime_kuma
UPTIME_KUMA_HABILITADA=true
UPTIME_KUMA_URL=https://uptime.interno.hefesto.cl
UPTIME_KUMA_CLAVE_API=<desde el gestor de secretos>
```

## 5. Comprobar con la empresa

1. `GET /api/panel/resumen` debe devolver **seis tarjetas**, una por sistema,
   con su estado y la hora de actualización.
2. `GET /api/panel/sitios` debe traer el sitio creado y nada mas.
3. `GET /api/panel/respaldos` debe decir `sin_registros: true` si todavia no
   hay historial. **No** debe mostrar un respaldo "exitoso" de mentira.
4. Con el token de **otra** empresa: cada endpoint debe devolver 404 o cero
   filas. Si devuelve algo, hay una fuga y hay que detener el despliegue.

## 6. Mostrarle el panel al cliente

La conversacion que conviene tener, porque el panel cambia la relacion:

- Esto **muestra**, no **controla**. Si necesita un respaldo ahora, tiene que
  llamarnos.
- Los datos no son en vivo: se actualizan cada ciertos minutos, y cada pantalla
  dice de cuando son.
- Si una tarjeta aparece apagada, es porque todavia no la conectamos, y se les
  va a decir. No vamos a mostrarles ceros para que parezca que funciona.