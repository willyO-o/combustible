<?php

namespace Database\Factories;

use App\Models\Area;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Area>
 */
class AreaFactory extends Factory
{
    protected $model = Area::class;

    public function definition(): array
    {
        return [
            'nombre_area' => fake()->unique()->word(),
            'descripcion_area' => fake()->sentence(),
            'estado_area' => 'ACTIVO',
        ];
    }
}
