<?php

namespace Database\Factories;

use App\Models\VehiculoExterno;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehiculoExterno>
 */
class VehiculoExternoFactory extends Factory
{
    protected $model = VehiculoExterno::class;

    public function definition(): array
    {
        return [
            'nro_placa' => fake()->unique()->bothify('####-???'),
            'propietario' => fake()->name(),
        ];
    }
}
