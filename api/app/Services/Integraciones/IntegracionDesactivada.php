<?php

declare(strict_types=1);

namespace App\Services\Integraciones;

use App\Enums\Sistema;

/**
 * Integracion que existe en el catalogo pero que NO consulta nada.
 *
 * Es el lugar donde vive una integracion sin verificar. No es un stub que
 * devuelve ceros: no devuelve nada. La tarjeta del cliente dice "desactivada"
 * y explica por que, que es la diferencia entre un panel honesto y uno que
 * muestra ceros falsos que el cliente toma por un respaldo exitoso.
 */
final class IntegracionDesactivada implements Integracion
{
    public function __construct(
        private readonly Sistema $sistema,
        private readonly string $motivo,
    ) {}

    public function sistema(): Sistema
    {
        return $this->sistema;
    }

    public function estadoLocal(): ResultadoIntegracion
    {
        return ResultadoIntegracion::desactivada($this->sistema, $this->motivo);
    }

    public function consultar(): array
    {
        // Nunca se llega aqui: ConsultaCacheada revisa estadoLocal() antes.
        // Si se llegara, es preferible fallar en silencio a devolver ceros.
        return [];
    }

    public function motivo(): string
    {
        return $this->motivo;
    }
}