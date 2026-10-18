<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Verificacion del contenido del respaldo.
 *
 * "Pendiente" y "No aplica" NO son lo mismo que "fallido", y el panel los
 * muestra distintos para que el cliente no asuma que un respaldo sin
 * verificacion esta correcto.
 */
enum EstadoVerificacion: string
{
    case Verificado = 'verificado';
    case Fallido = 'fallido';
    case Pendiente = 'pendiente';
    case NoAplica = 'no_aplica';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Verificado => 'Verificado',
            self::Fallido => 'Verificacion fallo',
            self::Pendiente => 'Sin verificar',
            self::NoAplica => 'No aplica',
        };
    }
}