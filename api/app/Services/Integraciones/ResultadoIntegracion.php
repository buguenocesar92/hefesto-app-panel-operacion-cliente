<?php

declare(strict_types=1);

namespace App\Services\Integraciones;

use App\Enums\EstadoIntegracion;
use App\Enums\Sistema;
use Carbon\CarbonImmutable;

/**
 * Resultado de consultar un sistema externo.
 *
 * Es inmutable y SIEMPRE completo: incluso cuando el sistema no respondio.
 * Un resultado con estado "no disponible" y datos vacios es preferible a una
 * excepcion, porque el tablero completo depende de que se pueda pintar.
 */
final readonly class ResultadoIntegracion
{
    /**
     * @param  array<string, mixed>  $datos  Indicadores agregados. Nunca contenido.
     * @param  CarbonImmutable|null  $consultadoEn  Momento real de la consulta, no de la lectura de cache.
     */
    public function __construct(
        public Sistema $sistema,
        public EstadoIntegracion $estado,
        public string $mensaje,
        public array $datos = [],
        public ?CarbonImmutable $consultadoEn = null,
        public ?int $duracionMs = null,
        public bool $desdeCache = false,
    ) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function disponible(
        Sistema $sistema,
        string $mensaje,
        array $datos,
        CarbonImmutable $consultadoEn,
        int $duracionMs,
    ): self {
        return new self($sistema, EstadoIntegracion::Disponible, $mensaje, $datos, $consultadoEn, $duracionMs);
    }

    public static function noDisponible(
        Sistema $sistema,
        string $mensaje,
        ?CarbonImmutable $consultadoEn = null,
    ): self {
        return new self($sistema, EstadoIntegracion::NoDisponible, $mensaje, [], $consultadoEn);
    }

    public static function desactivada(Sistema $sistema, string $motivo): self
    {
        return new self($sistema, EstadoIntegracion::Desactivada, $motivo);
    }

    public static function noConfigurada(Sistema $sistema, string $mensaje = 'Este sistema todavia no esta configurado en el panel.'): self
    {
        return new self($sistema, EstadoIntegracion::NoConfigurada, $mensaje);
    }

    /** Copia marcada como servida desde cache. */
    public function desdeCache(): self
    {
        return new self(
            $this->sistema,
            $this->estado,
            $this->mensaje,
            $this->datos,
            $this->consultadoEn,
            $this->duracionMs,
            true,
        );
    }

    /** @return array<string, mixed> */
    public function aArray(): array
    {
        return [
            'sistema' => $this->sistema->value,
            'estado' => $this->estado->value,
            'mensaje' => $this->mensaje,
            'consultado_en' => $this->consultadoEn?->toIso8601String(),
            'desde_cache' => $this->desdeCache,
        ];
    }
}