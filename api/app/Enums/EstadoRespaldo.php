<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Resultado de un respaldo, segun lo reporta el sistema de origen.
 *
 * Nunca se infiere a partir de "no hay datos": si no hay registro, el panel
 * dice que no hay registro, no que el respaldo salio bien.
 */
enum EstadoRespaldo: string
{
    case Exitoso = 'exitoso';
    case Fallido = 'fallido';
    case EnProceso = 'en_proceso';
    case Interrumpido = 'interrumpido';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Exitoso => 'Completado',
            self::Fallido => 'Con errores',
            self::EnProceso => 'En proceso',
            self::Interrumpido => 'Interrumpido',
        };
    }

    public function esFalla(): bool
    {
        return $this === self::Fallido || $this === self::Interrumpido;
    }
}