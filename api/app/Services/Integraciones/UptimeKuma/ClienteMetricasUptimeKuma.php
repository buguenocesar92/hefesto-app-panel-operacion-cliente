<?php

declare(strict_types=1);

namespace App\Services\Integraciones\UptimeKuma;

use App\Enums\EstadoIntegracion;
use App\Enums\Sistema;
use App\Services\Integraciones\Integracion;
use App\Services\Integraciones\ResultadoIntegracion;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use RuntimeException;

/**
 * Integracion con Uptime Kuma, por su endpoint de metricas Prometheus.
 *
 * QUE ESTA VERIFICADO (fuente al pie del metodo consultar())
 * -------------------------------------------------------
 * Segun la wiki oficial de Uptime Kuma (seccion "Prometheus API Keys", que
 * exige version 1.21 o superior):
 *
 *  - El endpoint de metricas es `/metrics`.
 *  - Se protege con autenticacion basica cuya clave se pasa en la cabecera
 *    `Authorization`, con el usuario VACIO: `curl -u":<clave>"`.
 *  - "The API key cannot be used to access the web interface": la clave no
 *    abre la interfaz web, solo permite leer metricas. Es la razon por la que
 *    esta integracion puede existir: la credencial es de solo lectura.
 *  - Al agregar la primera clave, la autenticacion basica queda
 *    permanentemente deshabilitada para ese endpoint.
 *
 * QUE NO ESTA VERIFICADO
 * ----------------------
 * Los nombres y las etiquetas de las metricas que publica Uptime Kuma no
 * estan documentados. Por eso esta clase NO decide que significa ningun
 * valor: devuelve las series tal cual y el servicio de sitios las cruza con
 * los hosts registrados del cliente.
 *
 * Fuente: https://github.com/louislam/uptime-kuma/wiki/Prometheus-API-Keys
 */
final class ClienteMetricasUptimeKuma implements Integracion
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly LectorPrometheus $lector,
    ) {}

    public function sistema(): Sistema
    {
        return Sistema::UptimeKuma;
    }

    public function estadoLocal(): ResultadoIntegracion
    {
        if (! config('integraciones.uptime_kuma.habilitada')) {
            return ResultadoIntegracion::desactivada(
                Sistema::UptimeKuma,
                $this->motivoDesactivacion()
            );
        }

        if (config('integraciones.uptime_kuma.url') === null
            || config('integraciones.uptime_kuma.clave_api') === null) {
            return ResultadoIntegracion::noConfigurada(
                Sistema::UptimeKuma,
                'Falta la URL o la clave de API de Uptime Kuma en el gestor de secretos.'
            );
        }

        return new ResultadoIntegracion(
            Sistema::UptimeKuma,
            EstadoIntegracion::Disponible,
            'Consulta lista.',
        );
    }

    /**
     * @return array{series: list<array{nombre: string, etiquetas: array<string, string>, valor: float}>, total_series: int}
     */
    public function consultar(): array
    {
        $url = rtrim((string) config('integraciones.uptime_kuma.url'), '/').'/metrics';

        $clave = (string) config('integraciones.uptime_kuma.clave_api');

        $respuesta = $this->peticion()
            ->withHeader('Authorization', 'Basic '.base64_encode(':'.$clave))
            ->accept('text/plain')
            ->get($url);

        if (! $respuesta->successful()) {
            // No se guarda ni se muestra el cuerpo de la respuesta: puede
            // traer detalles internos de la instalacion.
            throw new RuntimeException(sprintf(
                'Uptime Kuma respondio con estado HTTP %d.',
                $respuesta->status()
            ));
        }

        $series = $this->lector->leer($respuesta->body());

        return [
            'series' => $series,
            'total_series' => count($series),
        ];
    }

    private function peticion(): PendingRequest
    {
        return $this->http
            ->timeout((int) config('panel.timeout_segundos'))
            ->connectTimeout((int) config('panel.timeout_segundos'))
            ->withOptions(['verify' => (bool) config('integraciones.uptime_kuma.verificar_tls')]);
    }

    /** Marca de tiempo del momento de la consulta, para mostrarla al cliente. */
    public function ahora(): CarbonImmutable
    {
        return CarbonImmutable::now();
    }

    private function motivoDesactivacion(): string
    {
        $verificacion = config('integraciones.uptime_kuma.verificacion');

        return sprintf(
            'Integracion apagada en esta instalacion. %s',
            is_array($verificacion) ? (string) ($verificacion['pendiente'] ?? '') : ''
        );
    }
}