<?php

namespace Database\Factories;

use App\Models\Persona;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Persona>
 */
class PersonaFactory extends Factory
{
    protected $model = Persona::class;

    public function definition(): array
    {
        return [
            'ci' => fake()->unique()->numerify('########'),
            'nombres' => fake()->firstName(),
            'paterno' => fake()->lastName(),
            'materno' => fake()->lastName(),
            'celular' => fake()->numerify('7#######'),
            'direccion' => fake()->address(),
            'fecha_nacimiento' => fake()->date(),
            'estado_persona' => 'ACTIVO',
        ];
    }
}
