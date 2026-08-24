<?php

namespace Database\Factories;

use App\Models\CargaMaterial;
use App\Models\Material;
use App\Models\User;
use App\Models\Viaje;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Viaje>
 */
class ViajeFactory extends Factory
{
    protected $model = Viaje::class;

    public function definition(): array
    {
        return [
            'id_carga_material' => CargaMaterial::factory(),
            'id_material' => Material::factory(),
            'id_usuario_registro' => User::factory(),
            'foto' => 'control-cargas/viajes/'.fake()->uuid().'.jpg',
            'origen' => fake()->streetName(),
            'destino' => fake()->streetName(),
            'detalle' => fake()->sentence(),
        ];
    }
}
