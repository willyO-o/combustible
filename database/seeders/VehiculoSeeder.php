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
                'codigo' => 'PM-EQP-0023',
                'anio' => '2020',
                'marca' => 'Toyota',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 3, // Diésel
                'id_tipo_vehiculo' => 1,
                'fotografia' => 'vehiculos/CaPy03xlQuLTZhLkBPlZi0QlXmfDKkDpmwjgd5BS.jpg',
                'tipo_medicion' => 'horometro',
            ],
            [
                'nro_placa' => 'OR-5678',
                'codigo' => 'PM-EQP-0024',
                'anio' => '2021',
                'marca' => 'Hyundai',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 3, // Diésel
                'id_tipo_vehiculo' => 2, //
                'fotografia' => 'vehiculos/CaPy03xlQuLTZhLkBPlZi0QlXmfDKkDpmwjgd5BS.jpg',
                'tipo_medicion' => 'horometro',
            ],
            [
                'nro_placa' => 'SC-9101',
                'codigo' => 'PM-YEM-0010',
                'anio' => '2019',
                'marca' => 'Isuzu',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 3, // Diésel
                'id_tipo_vehiculo' => 3, // volquete
                'fotografia' => 'vehiculos/CaPy03xlQuLTZhLkBPlZi0QlXmfDKkDpmwjgd5BS.jpg',
                'tipo_medicion' => 'kilometraje',
            ],
            [
                'nro_placa' => 'CB-1121',
                'codigo' => 'PM-EQP-0026',
                'anio' => '2022',
                'marca' => 'Kia',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 3, // diésel
                'id_tipo_vehiculo' => 4, // cisterna
                'fotografia' => 'vehiculos/CaPy03xlQuLTZhLkBPlZi0QlXmfDKkDpmwjgd5BS.jpg',
                'tipo_medicion' => 'kilometraje',
            ],
            [
                'nro_placa' => 'PT-3141',
                'anio' => '2018',
                'codigo' => 'PM-EQP-0027',
                'marca' => 'Nissan',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 3, // Diésel
                'id_tipo_vehiculo' => 4, // cisterna
                'fotografia' => 'vehiculos/CaPy03xlQuLTZhLkBPlZi0QlXmfDKkDpmwjgd5BS.jpg',
                'tipo_medicion' => 'kilometraje',
            ],
            [
                'nro_placa' => 'LP-5161',
                'codigo' => 'PM-YEM-0011',
                'anio' => '2023',
                'marca' => 'Chevrolet',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 3, // Diésel
                'id_tipo_vehiculo' => 2, // Automóvil
                'fotografia' => 'vehiculos/CaPy03xlQuLTZhLkBPlZi0QlXmfDKkDpmwjgd5BS.jpg',
                'tipo_medicion' => 'horometro',
            ],
        ];

        foreach ($vehiculos as $vehiculo) {
            Vehiculo::updateOrCreate(
                ['nro_placa' => $vehiculo['nro_placa']],
                [
                    'anio' => $vehiculo['anio'],
                    'marca' => $vehiculo['marca'],
                    'codigo' => $vehiculo['codigo'],
                    'estado_vehiculo' => $vehiculo['estado_vehiculo'],
                    'id_tipo_combustible' => $vehiculo['id_tipo_combustible'],
                    'id_tipo_vehiculo' => $vehiculo['id_tipo_vehiculo'],
                    'fotografia' => $vehiculo['fotografia'],
                    'tipo_medicion' => $vehiculo['tipo_medicion'],
                ]
            );
        }

        $this->command->info('✓ Vehículos creados exitosamente');
    }
}
