<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Asignacion;

class AsignacionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $asignaciones = [
            [
                'id' => 1,
                'id_vehiculo' => 1,
                'id_conductor' => 1,
                'fecha_asignacion' => '2023-01-01',
                'estado_asignacion' => 'ACTIVO',
                'horometro_inicial' => 150,
            ],
            [
                'id' => 2,
                'id_vehiculo' => 2,
                'id_conductor' => 2,
                'fecha_asignacion' => '2023-02-01',
                'estado_asignacion' => 'ACTIVO',
                'horometro_inicial' => 200,
            ],
            [
                'id' => 3,
                'id_vehiculo' => 3,
                'id_conductor' => 3,
                'fecha_asignacion' => '2023-03-01',
                'estado_asignacion' => 'ACTIVO',
                'horometro_inicial' => 250,
            ],
        ];

        foreach ($asignaciones as $asignacion) {
            Asignacion::updateOrCreate(
                ['id' => $asignacion['id']],
                [
                    'id_vehiculo' => $asignacion['id_vehiculo'],
                    'id_conductor' => $asignacion['id_conductor'],
                    'fecha_asignacion' => $asignacion['fecha_asignacion'],
                    'estado_asignacion' => $asignacion['estado_asignacion'],
                    'horometro_inicial' => $asignacion['horometro_inicial'],
                ]
            );
        }
    }
}
