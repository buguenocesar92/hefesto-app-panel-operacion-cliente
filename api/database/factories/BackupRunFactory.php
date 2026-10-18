<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstadoRespaldo;
use App\Enums\EstadoVerificacion;
use App\Models\BackupRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BackupRun>
 */
class BackupRunFactory extends Factory
{
    protected $model = BackupRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'origen' => 'kopia (prueba)',
            'iniciado_en' => now()->subHours(6),
            'terminado_en' => now()->subHours(6)->addMinutes(8),
            'estado' => EstadoRespaldo::Exitoso,
            'verificacion' => EstadoVerificacion::Verificado,
            'verificado_en' => now()->subHours(6)->addMinutes(9),
            'archivos' => 1000,
            'bytes' => 900_000_000,
            'duracion_segundos' => 480,
        ];
    }

    public function fallido(): static
    {
        return $this->state(fn (): array => [
            'estado' => EstadoRespaldo::Fallido,
            'verificacion' => EstadoVerificacion::Fallido,
            'verificado_en' => null,
            'archivos' => null,
            'bytes' => null,
        ]);
    }
}