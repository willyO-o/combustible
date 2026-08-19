<?php

namespace Database\Factories;

use App\Models\GrupoVehiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GrupoVehiculo>
 */
class GrupoVehiculoFactory extends Factory
{
    protected $model = GrupoVehiculo::class;

    public function definition(): array
    {
        return [
            'grupo_vehiculo' => fake()->unique()->words(3, true),
            'estado_grupo_vehiculo' => 'ACTIVO',
        ];
    }
}
