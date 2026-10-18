<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Integraciones\UptimeKuma\LectorPrometheus;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas del lector de metricas en formato de exposicion de Prometheus.
 *
 * Aqui se comprueba lo unico que este lector es responsable de: entender el
 * formato. Lo que SIGNIFICA cada metrica no es responsabilidad suya, y
 * deliberadamente no lo decide.
 */
class LectorPrometheusTest extends TestCase
{
    public function test_lee_una_serie_sin_etiquetas(): void
    {
        $series = (new LectorPrometheus())->leer("uptime_kuma_algo 1\n");

        $this->assertCount(1, $series);
        $this->assertSame([], $series[0]['etiquetas']);
        $this->assertSame(1.0, $series[0]['valor']);
    }

    public function test_lee_una_serie_con_etiquetas(): void
    {
        $cuerpo = 'monitor_status{monitor_url="https://tienda.cl",monitor_hostname="tienda.cl"} 1';

        $series = (new LectorPrometheus())->leer($cuerpo);

        $this->assertCount(1, $series);
        $this->assertSame('monitor_status', $series[0]['nombre']);
        $this->assertSame('tienda.cl', $series[0]['etiquetas']['monitor_hostname']);
        $this->assertSame(1.0, $series[0]['valor']);
    }

    public function test_ignora_las_lineas_de_comentario(): void
    {
        $cuerpo = <<<'TEXTO'
        # HELP monitor_status Estado del monitor.
        # TYPE monitor_status gauge
        monitor_status{monitor_hostname="tienda.cl"} 1
        TEXTO;

        $series = (new LectorPrometheus())->leer($cuerpo);

        $this->assertCount(1, $series);
        $this->assertSame('monitor_status', $series[0]['nombre']);
    }

    public function test_un_valor_que_no_es_numerico_no_se_convierte_en_cero(): void
    {
        $series = (new LectorPrometheus())->leer("metica_rota{etiqueta=\"x\"} no-es-un-numero");

        // Devolver 0 seria un dato falso: el panel mostraria "caido" por un
        // error de formato.
        $this->assertSame([], $series);
    }

    public function test_lee_valores_con_decimales_y_actividad(): void
    {
        $series = (new LectorPrometheus())->leer("tiempo_respuesta 0.256\n");

        $this->assertSame(0.256, $series[0]['valor']);
    }

    public function test_una_etiqueta_con_comas_no_parte_el_conjunto(): void
    {
        $series = (new LectorPrometheus())->leer('info{descripcion="uno, dos, tres",otro="4"} 1');

        $this->assertSame('uno, dos, tres', $series[0]['etiquetas']['descripcion']);
        $this->assertSame('4', $series[0]['etiquetas']['otro']);
    }

    public function test_cuerpo_vacio_devuelve_una_lista_vacia(): void
    {
        $this->assertSame([], (new LectorPrometheus())->leer(''));
    }
}