<?php

namespace Database\Factories;

use App\Models\TipoVehiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoVehiculo>
 */
class TipoVehiculoFactory extends Factory
{
    protected $model = TipoVehiculo::class;

    public function definition(): array
    {
        return [
            'tipo_vehiculo' => fake()->unique()->word(),
            'estado_tipo_vehiculo' => 'ACTIVO',
        ];
    }
}
