<?php

namespace Database\Seeders;

use App\Models\TipoVehiculo;
use Illuminate\Database\Seeder;

class TipoVehiculoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tiposVehiculos = [
            [
                'tipo_vehiculo' => 'Camioneta',
                'estado_tipo_vehiculo' => 'ACTIVO',
            ],
            [
                'tipo_vehiculo' => 'Automóvil',
                'estado_tipo_vehiculo' => 'ACTIVO',
            ],
            [
                'tipo_vehiculo' => 'Minibús',
                'estado_tipo_vehiculo' => 'ACTIVO',
            ],
            [
                'tipo_vehiculo' => 'Autobús',
                'estado_tipo_vehiculo' => 'ACTIVO',
            ],
            [
                'tipo_vehiculo' => 'Motocicleta',
                'estado_tipo_vehiculo' => 'ACTIVO',
            ],
            [
                'tipo_vehiculo' => 'Bicicleta',
                'estado_tipo_vehiculo' => 'ACTIVO',
            ],
        ];

        foreach ($tiposVehiculos as $tipo) {
            TipoVehiculo::updateOrCreate(
                ['tipo_vehiculo' => $tipo['tipo_vehiculo']],
                ['estado_tipo_vehiculo' => $tipo['estado_tipo_vehiculo']]
            );
        }

        $this->command->info('✓ Tipos de vehículos creados exitosamente');
    }
}
