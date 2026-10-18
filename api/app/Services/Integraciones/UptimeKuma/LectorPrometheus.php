<?php

declare(strict_types=1);

namespace App\Services\Integraciones\UptimeKuma;

/**
 * Lector del formato de exposicion de metricas de Prometheus.
 *
 * Por que existe un lector propio y no una libreria: la regla del proyecto
 * es no agregar dependencias que no podamos verificar, y el subconjunto que
 * necesitamos (lineas de serie con etiquetas, sin timestamps ni tipos
 *Histogram ni Summary) esta definido por el formato de exposicion de
 * Prometheus.
 *
 * LO QUE NO HACE: no interpreta el significado de ninguna metrica. El panel
 * no sabe que es `monitor_status` ni que valor significa "caido", porque eso
 * no esta documentado oficialmente. En vez de inventarlo, devuelve las series
 * crudas y el servicio de sitios busca, entre las etiquetas, la que coincide
 * con el host del sitio del cliente.
 *
 * @see https://prometheus.io/docs/instrumenting/exposition_formats/
 */
final class LectorPrometheus
{
    /**
     * @return list<array{nombre: string, etiquetas: array<string, string>, valor: float}>
     */
    public function leer(string $cuerpo): array
    {
        $series = [];

        foreach (preg_split('/\r\n|\r|\n/', $cuerpo) ?: [] as $linea) {
            $linea = trim($linea);

            if ($linea === '' || str_starts_with($linea, '#')) {
                continue;
            }

            $serie = $this->parsearLinea($linea);

            if ($serie !== null) {
                $series[] = $serie;
            }
        }

        return $series;
    }

    /**
     * @return array{nombre: string, etiquetas: array<string, string>, valor: float}|null
     */
    private function parsearLinea(string $linea): ?array
    {
        $llave = $this->extraerLlave($linea);

        if ($llave === null) {
            return null;
        }

        [$nombre, $etiquetas] = $llave;

        // El valor es el primer token tras la llave. Si no es numerico, la
        // linea se ignora en vez de inventar un cero.
        $resto = trim(substr($linea, strlen($nombre) + $this->longitudDelBloqueDeEtiquetas($linea)));

        if (! preg_match('/^[-+]?(\d+(\.\d+)?([eE][-+]?\d+)?|[Ii][Nn][Ff]|[Nn][Aa][Nn])/', $resto, $corte)) {
            return null;
        }

        return [
            'nombre' => $nombre,
            'etiquetas' => $etiquetas,
            'valor' => is_numeric($corte[0]) ? (float) $corte[0] : 0.0,
        ];
    }

    /**
     * @return array{0: string, 1: array<string, string>}|null
     */
    private function extraerLlave(string $linea): ?array
    {
        $posicionLlaves = strpos($linea, '{');

        if ($posicionLlaves === false) {
            // Serie sin etiquetas: "nombre 1".
            if (! preg_match('/^([a-zA-Z_:][a-zA-Z0-9_:]*)(\s|$)/', $linea, $corte)) {
                return null;
            }

            return [$corte[1], []];
        }

        $nombre = rtrim(substr($linea, 0, $posicionLlaves));

        if (! preg_match('/^[a-zA-Z_:][a-zA-Z0-9_:]*$/', $nombre)) {
            return null;
        }

        $cierre = strrpos($linea, '}');

        if ($cierre === false || $cierre < $posicionLlaves) {
            return null;
        }

        $etiquetas = $this->parsearEtiquetas(substr($linea, $posicionLlaves + 1, $cierre - $posicionLlaves - 1));

        return [$nombre, $etiquetas];
    }

    /** @return array<string, string> */
    private function parsearEtiquetas(string $texto): array
    {
        $etiquetas = [];

        // Split por comas que esten FUERA de comillas: un valor puede
        // contener comas.
        $partes = preg_split('/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', $texto) ?: [];

        foreach ($partes as $parte) {
            if (! preg_match('/^\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*"(.*)"\s*$/s', $parte, $corte)) {
                continue;
            }

            $etiquetas[$corte[1]] = $this->desescapar($corte[2]);
        }

        return $etiquetas;
    }

    private function desescapar(string $valor): string
    {
        return str_replace(
            ['\\n', '\\"', '\\\\'],
            ["\n", '"', '\\'],
            $valor
        );
    }

    /**
     * Longitud que ocupa "{...}" en la linea, contada desde su inicio, para
     * poder saltar la llave y leer el valor.
     */
    private function longitudDelBloqueDeEtiquetas(string $linea): int
    {
        $posicion = strpos($linea, '}');

        return $posicion === false ? 0 : $posicion + 1;
    }
}