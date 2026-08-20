<?php

namespace Database\Factories;

use App\Models\Conductor;
use App\Models\DocumentoConductor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentoConductor>
 */
class DocumentoConductorFactory extends Factory
{
    protected $model = DocumentoConductor::class;

    public function definition(): array
    {
        return [
            'id_conductor' => Conductor::factory(),
            'tipo_documento' => 'LICENCIA_DE_CONDUCIR',
            'numero_documento' => $this->faker->bothify('LIC-####'),
            'categoria' => 'B-2',
            'fecha_emision' => $this->faker->dateTimeBetween('-2 years', '-1 month')->format('Y-m-d'),
            'fecha_vencimiento' => $this->faker->dateTimeBetween('+1 month', '+2 years')->format('Y-m-d'),
            'estado_documento' => 'VIGENTE',
            'observacion' => null,
        ];
    }
}
