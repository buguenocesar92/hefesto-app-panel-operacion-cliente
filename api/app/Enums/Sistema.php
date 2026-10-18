<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Los sistemas que Hefesto administra y que el panel consulta.
 *
 * El orden es el que ve el cliente en la tarjeta de resumen.
 */
enum Sistema: string
{
    case UptimeKuma = 'uptime_kuma';
    case SigNoz = 'signoz';
    case Kopia = 'kopia';
    case Coolify = 'coolify';
    case CrowdSec = 'crowdsec';
    case Authentik = 'authentik';

    public function nombre(): string
    {
        return match ($this) {
            self::UptimeKuma => 'Uptime Kuma',
            self::SigNoz => 'SigNoz',
            self::Kopia => 'Kopia',
            self::Coolify => 'Coolify',
            self::CrowdSec => 'CrowdSec',
            self::Authentik => 'Authentik',
        };
    }

    /** Descripcion de una linea para el cliente: que le aporta este sistema. */
    public function resumen(): string
    {
        return match ($this) {
            self::UptimeKuma => 'Disponibilidad de sus sitios.',
            self::SigNoz => 'Metricas y estado de sus aplicaciones.',
            self::Kopia => 'Fecha y resultado del ultimo respaldo, y si fue verificado.',
            self::Coolify => 'Despliegues, dominios y estado de los recursos.',
            self::CrowdSec => 'Ataques detectados y bloqueos aplicados.',
            self::Authentik => 'Usuarios activos y estado del servicio.',
        };
    }

    /** Clave usada en config/integraciones.php. */
    public function claveConfig(): string
    {
        return $this->value;
    }
}