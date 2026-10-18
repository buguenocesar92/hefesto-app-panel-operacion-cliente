<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Semilla principal.
 *
 * En una instalacion nueva se corre SOLO esta semilla, y crea una PyME de
 * ejemplo. Los datos son ficticios y estan marcados como tales en el nombre
 * y en el RUT: nadie deberia confundirlos con una operacion real.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ClienteEjemploSeeder::class);
    }
}