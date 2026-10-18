<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de una integracion tal como se le muestra al cliente.
 *
 * La distincion importante es entre "no disponible" (esta configurada pero
 * el sistema no respondio o dio error) y "desactivada" (no hay credencial de
 * solo lectura, o la verificacion quedo pendiente). Son cosas distintas y
 * el cliente las ve distintas.
 *
 * "datos_viejos" existe para que nadie crea que ve datos en vivo: cuando la
 * cache supera el umbral, la tarjeta lo dice aunque el contenido siga
 * siendo real.
 */
enum EstadoIntegracion: string
{
    case Disponible = 'disponible';
    case DatosViejos = 'datos_viejos';
    case NoDisponible = 'no_disponible';
    case Desactivada = 'desactivada';
    case NoConfigurada = 'no_configurada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Disponible => 'Operando',
            self::DatosViejos => 'Datos antiguos',
            self::NoDisponible => 'No disponible',
            self::Desactivada => 'Desactivada',
            self::NoConfigurada => 'Sin configurar',
        };
    }

    /**
     * El panel sigue funcionando: una tarjeta degradada no puede arrastrar
     * al resto del tablero. Todo estado que no sea "Disponible" o
     * "DatosViejos" es una degradacion.
     */
    public function esDegradado(): bool
    {
        return $this !== self::Disponible;
    }

    /** Es una falla que Hefesto tiene que mirar (no una decision de diseño). */
    public function esFalla(): bool
    {
        return $this === self::NoDisponible;
    }

    /** El sistema nunca respondio porque no hay integracion que lo consulte. */
    public function estaAusente(): bool
    {
        return $this === self::Desactivada || $this === self::NoConfigurada;
    }
}