<?php

namespace Database\Seeders;

use App\Models\Conductor;
use Illuminate\Database\Seeder;

class ConductorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $conductores = [
            [
                'nombres' => 'Juan',
                'paterno' => 'García',
                'materno' => 'López',
                'foto' => 'conductor_1.jpg',
                'ci' => '1234567',
                'nro_licencia' => 'LIC-001',
                'categoria' => 'C',
                'celular' => '7123456789',
                'direccion' => 'Av. Principal 123',
                'fecha_nacimiento' => '1990-05-15',
                'estado_conductor' => 'ACTIVO',
            ],
            [
                'nombres' => 'María',
                'paterno' => 'Rodríguez',
                'materno' => 'Martínez',
                'foto' => 'conductor_2.jpg',
                'ci' => '1234568',
                'nro_licencia' => 'LIC-002',
                'categoria' => 'B',
                'celular' => '7123456790',
                'direccion' => 'Calle 5ta 456',
                'fecha_nacimiento' => '1992-08-20',
                'estado_conductor' => 'ACTIVO',
            ],
            [
                'nombres' => 'Carlos',
                'paterno' => 'Morales',
                'materno' => 'Sánchez',
                'foto' => 'conductor_3.jpg',
                'ci' => '1234569',
                'nro_licencia' => 'LIC-003',
                'categoria' => 'C',
                'celular' => '7123456791',
                'direccion' => 'Pasaje 2 789',
                'fecha_nacimiento' => '1988-12-10',
                'estado_conductor' => 'ACTIVO',
            ],
            [
                'nombres' => 'Ana',
                'paterno' => 'Flores',
                'materno' => 'Gutierrez',
                'foto' => 'conductor_4.jpg',
                'ci' => '1234570',
                'nro_licencia' => 'LIC-004',
                'categoria' => 'P',
                'celular' => '7123456792',
                'direccion' => 'Boulevard Central 321',
                'fecha_nacimiento' => '1995-03-25',
                'estado_conductor' => 'ACTIVO',
            ],
            [
                'nombres' => 'Roberto',
                'paterno' => 'Hernández',
                'materno' => 'Ramos',
                'foto' => 'conductor_5.jpg',
                'ci' => '1234571',
                'nro_licencia' => 'LIC-005',
                'categoria' => 'C',
                'celular' => '7123456793',
                'direccion' => 'Avenida Este 654',
                'fecha_nacimiento' => '1985-07-08',
                'estado_conductor' => 'ACTIVO',
            ],
        ];

        foreach ($conductores as $conductor) {
            Conductor::updateOrCreate(
                ['ci' => $conductor['ci']],
                [
                    'nombres' => $conductor['nombres'],
                    'paterno' => $conductor['paterno'],
                    'materno' => $conductor['materno'],
                    'foto' => $conductor['foto'],
                    'nro_licencia' => $conductor['nro_licencia'],
                    'categoria' => $conductor['categoria'],
                    'celular' => $conductor['celular'],
                    'direccion' => $conductor['direccion'],
                    'fecha_nacimiento' => $conductor['fecha_nacimiento'],
                    'estado_conductor' => $conductor['estado_conductor'],
                ]
            );
        }

        $this->command->info('✓ Conductores creados exitosamente');
    }
}
