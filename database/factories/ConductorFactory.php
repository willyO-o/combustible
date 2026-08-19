<?php

namespace Database\Factories;

use App\Models\Conductor;
use App\Models\Persona;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conductor>
 */
class ConductorFactory extends Factory
{
    protected $model = Conductor::class;

    public function definition(): array
    {
        return [
            'id' => Persona::factory(),
            'estado_conductor' => 'ACTIVO',
        ];
    }
}
