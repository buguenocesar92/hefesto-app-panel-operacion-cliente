<?php

declare(strict_types=1);

namespace App\Services\Cache;

use App\Enums\EstadoIntegracion;
use App\Enums\Sistema;
use App\Services\Integraciones\Integracion;
use App\Services\Integraciones\ResultadoIntegracion;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cache de consultas a los sistemas externos.
 *
 * POR QUE EXISTE: si el cliente recarga la pagina cinco veces en un minuto,
 * los sistemas externos NO se consultan cinco veces. Cada sistema tiene su
 * TTL (config/panel.php) y el panel muestra siempre de cuando son los datos,
 * para que nadie los tome por datos en vivo.
 *
 * DEGRADACION: si la consulta falla, NO se lanza la excepcion. Se devuelve un
 * resultado "no disponible" y el resto del tablero se sigue pintando. Un
 * backing service caido no puede dejar en blanco la pantalla del cliente.
 *
 * La clave de cache incluye el tenant: dos Pymes nunca comparten la misma
 * entrada aunque consulten el mismo sistema.
 */
final class ConsultaCacheada
{
    public function __construct(private readonly Repository $cache) {}

    /**
     * @template T
     *
     * @param  Integracion  $integracion
     * @param  Closure(): T  $consulta  Solo se invoca si hay que refrescar.
     * @return ResultadoIntegracion
     */
    public function obtener(Integracion $integracion, \Closure $consulta): ResultadoIntegracion
    {
        $sistema = $integracion->sistema();

        // Si la integracion esta apagada o incompleta, se dice de entrada,
        // sin tocar cache ni red.
        $estadoLocal = $integracion->estadoLocal();

        if ($estadoLocal->estado->estaAusente()) {
            return $estadoLocal;
        }

        $clave = $this->clave($sistema);
        $ttl = (int) config('panel.ttl_por_sistema.'.$sistema->value);

        $guardado = $this->cache->get($clave);

        if (is_array($guardado) && isset($guardado['estado'], $guardado['consultado_en'])) {
            $resultado = $this->reconstruir($sistema, $guardado);

            // Si la cache ya supero el umbral de "datos antiguos", se marca
            // como tal, pero el contenido sigue siendo real.
            return $this->marcarSiEnvejecio($resultado);
        }

        return $this->refrescar($integracion, $consulta, $clave, $ttl);
    }

    /**
     * Fuerza la consulta aunque haya cache. Se usa por el comando de
     * mantenimiento, nunca por un clic del cliente.
     */
    public function refrescarAhora(Integracion $integracion, \Closure $consulta): ResultadoIntegracion
    {
        $sistema = $integracion->sistema();
        $clave = $this->clave($sistema);
        $ttl = (int) config('panel.ttl_por_sistema.'.$sistema->value);

        return $this->refrescar($integracion, $consulta, $clave, $ttl);
    }

    private function refrescar(Integracion $integracion, \Closure $consulta, string $clave, int $ttl): ResultadoIntegracion
    {
        $sistema = $integracion->sistema();
        $inicio = CarbonImmutable::now();

        try {
            $datos = $consulta();
        } catch (Throwable $e) {
            // Se registra el detalle para Hefesto (tiene el motivo real) y se
            // devuelve un mensaje generico para el cliente. La excepcion
            // puede contener URLs internas o mensajes del sistema remoto.
            Log::warning('Consulta a integracion fallida.', [
                'sistema' => $sistema->value,
                'excepcion' => $e::class,
            ]);

            $resultado = ResultadoIntegracion::noDisponible(
                $sistema,
                'El sistema no respondio. Hefesto fue notificado.',
                $inicio,
            );

            // Un fallo NO se cachea por el TTL completo: se cachea poco
            // tiempo, para reintentar sin esperar a que el cliente recargue.
            $this->cache->put($clave, [
                'estado' => $resultado->estado->value,
                'mensaje' => $resultado->mensaje,
                'consultado_en' => $inicio->toIso8601String(),
                'datos' => [],
            ], now()->addSeconds(30));

            return $resultado;
        }

        $fin = CarbonImmutable::now();
        $resultado = ResultadoIntegracion::disponible(
            $sistema,
            'Consulta correcta.',
            is_array($datos) ? $datos : [],
            $fin,
            (int) $fin->diffInMilliseconds($inicio),
        );

        $this->cache->put($clave, [
            'estado' => $resultado->estado->value,
            'mensaje' => $resultado->mensaje,
            'consultado_en' => $fin->toIso8601String(),
            'datos' => $resultado->datos,
        ], $ttl > 0 ? now()->addSeconds($ttl) : now()->addHour());

        return $resultado;
    }

    /** @param array<string, mixed> $guardado */
    private function reconstruir(Sistema $sistema, array $guardado): ResultadoIntegracion
    {
        $consultadoEn = CarbonImmutable::parse((string) $guardado['consultado_en']);

        return new ResultadoIntegracion(
            sistema: $sistema,
            estado: EstadoIntegracion::from((string) $guardado['estado']),
            mensaje: (string) ($guardado['mensaje'] ?? ''),
            datos: is_array($guardado['datos'] ?? null) ? $guardado['datos'] : [],
            consultadoEn: $consultadoEn,
            desdeCache: true,
        );
    }

    private function marcarSiEnvejecio(ResultadoIntegracion $resultado): ResultadoIntegracion
    {
        if ($resultado->estado !== EstadoIntegracion::Disponible || $resultado->consultadoEn === null) {
            return $resultado;
        }

        $umbral = (int) config('panel.umbral_datos_viejos.'.$resultado->sistema->value);
        $edad = CarbonImmutable::now()->diffInSeconds($resultado->consultadoEn);

        if ($umbral > 0 && $edad > $umbral) {
            return new ResultadoIntegracion(
                sistema: $resultado->sistema,
                estado: EstadoIntegracion::DatosViejos,
                mensaje: 'La informacion mostrada es anterior al ultimo refresco programado.',
                datos: $resultado->datos,
                consultadoEn: $resultado->consultadoEn,
                desdeCache: true,
            );
        }

        return $resultado;
    }

    /**
     * Clave de cache. Incluye el tenant resuelto: es la barrera que impide
     * que los datos de una PyME aparezcan en la pantalla de otra.
     */
    public function clave(Sistema $sistema): string
    {
        $tenantId = \App\Services\Tenancy\ContextoTenant::actual() ?? 'sin-tenant';

        return sprintf('consulta:%s:%s', $tenantId, $sistema->value);
    }

    /** Almacacen dedicado del panel, separado de la cache de framework. */
    public static function almacen(): Repository
    {
        return Cache::store('panel');
    }
}