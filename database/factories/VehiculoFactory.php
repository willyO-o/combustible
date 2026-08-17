<?php

namespace Database\Factories;

use App\Models\TipoCombustible;
use App\Models\TipoVehiculo;
use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehiculo>
 */
class VehiculoFactory extends Factory
{
    protected $model = Vehiculo::class;

    public function definition(): array
    {
        return [
            'nro_placa' => fake()->unique()->bothify('###???'),
            'codigo' => fake()->unique()->bothify('VH-###'),
            'anio' => (string) fake()->numberBetween(2010, 2025),
            'marca' => fake()->word(),
            'modelo' => (string) fake()->numberBetween(2010, 2025),
            'estado_vehiculo' => 'ACTIVO',
            'id_tipo_combustible' => TipoCombustible::factory(),
            'id_tipo_vehiculo' => TipoVehiculo::factory(),
            'tipo_medicion' => 'kilometraje',
        ];
    }
}
