<?php

namespace Database\Factories;

use App\Models\TipoCombustible;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoCombustible>
 */
class TipoCombustibleFactory extends Factory
{
    protected $model = TipoCombustible::class;

    public function definition(): array
    {
        return [
            'tipo_combustible' => fake()->unique()->word(),
            'estado_tipo_combustible' => 'ACTIVO',
        ];
    }
}
