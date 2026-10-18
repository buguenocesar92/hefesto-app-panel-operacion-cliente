<?php

declare(strict_types=1);

namespace App\Services\Integraciones;

use App\Enums\Sistema;

/**
 * Contrato de toda integracion con un sistema de Hefesto.
 *
 * REGLA QUE GOBIERNA ESTA INTERFAZ: una integracion NO puede escribir en el
 * sistema externo. No hay metodos de escritura, no los habra, y el cliente
 * HTTP que usan las integraciones no tiene forma de hacer un POST.
 *
 * Si una integracion no puede cumplir el contrato de solo lectura, no se
 * implementa: se deja desactivada y documentada.
 */
interface Integracion
{
    public function sistema(): Sistema;

    /**
     * Estado SIN llamar al sistema externo: si esta habilitada, si le falta
     * configuracion. Sirve para pintar la tarjeta antes de consultar nada.
     */
    public function estadoLocal(): ResultadoIntegracion;

    /**
     * Consulta real al sistema. Puede lanzar excepcion: quien la llama
     * (ConsultaCacheada) la traduce a "no disponible".
     *
     * @return array<string, mixed> Indicadores agregados, nunca contenido.
     */
    public function consultar(): array;
}