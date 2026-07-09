<?php

namespace Database\Seeders;

use App\Models\Vehiculo;
use Illuminate\Database\Seeder;

class VehiculoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vehiculos = [
            [
                'nro_placa' => 'LP-1234',
                'anio' => '2020',
                'marca' => 'Toyota',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 3, // Diésel
                'id_tipo_vehiculo' => 1, // Camioneta
                'fotografia' => 'vehiculo_1.jpg',
            ],
            [
                'nro_placa' => 'OR-5678',
                'anio' => '2021',
                'marca' => 'Hyundai',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 1, // Gasolina 95
                'id_tipo_vehiculo' => 2, // Automóvil
                'fotografia' => 'vehiculo_2.jpg',
            ],
            [
                'nro_placa' => 'SC-9101',
                'anio' => '2019',
                'marca' => 'Isuzu',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 3, // Diésel
                'id_tipo_vehiculo' => 3, // Minibús
                'fotografia' => 'vehiculo_3.jpg',
            ],
            [
                'nro_placa' => 'CB-1121',
                'anio' => '2022',
                'marca' => 'Kia',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 1, // Gasolina 95
                'id_tipo_vehiculo' => 1, // Camioneta
                'fotografia' => 'vehiculo_4.jpg',
            ],
            [
                'nro_placa' => 'PT-3141',
                'anio' => '2018',
                'marca' => 'Nissan',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 3, // Diésel
                'id_tipo_vehiculo' => 4, // Autobús
                'fotografia' => 'vehiculo_5.jpg',
            ],
            [
                'nro_placa' => 'LP-5161',
                'anio' => '2023',
                'marca' => 'Chevrolet',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 2, // Gasolina 97
                'id_tipo_vehiculo' => 2, // Automóvil
                'fotografia' => 'vehiculo_6.jpg',
            ],
        ];

        foreach ($vehiculos as $vehiculo) {
            Vehiculo::updateOrCreate(
                ['nro_placa' => $vehiculo['nro_placa']],
                [
                    'anio' => $vehiculo['anio'],
                    'marca' => $vehiculo['marca'],
                    'estado_vehiculo' => $vehiculo['estado_vehiculo'],
                    'id_tipo_combustible' => $vehiculo['id_tipo_combustible'],
                    'id_tipo_vehiculo' => $vehiculo['id_tipo_vehiculo'],
                    'fotografia' => $vehiculo['fotografia'],
                ]
            );
        }

        $this->command->info('✓ Vehículos creados exitosamente');
    }
}
