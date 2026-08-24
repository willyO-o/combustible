<?php

namespace Database\Factories;

use App\Models\CargaMaterial;
use App\Models\User;
use App\Models\VehiculoExterno;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CargaMaterial>
 */
class CargaMaterialFactory extends Factory
{
    protected $model = CargaMaterial::class;

    public function definition(): array
    {
        return [
            'id_vehiculo_externo' => VehiculoExterno::factory(),
            'id_usuario_apertura' => User::factory(),
            'fecha_apertura' => now(),
            'estado_carga' => 'ABIERTA',
        ];
    }
}
