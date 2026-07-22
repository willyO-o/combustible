<?php

namespace Database\Seeders;

use App\Models\Conductor;
use App\Models\Persona;
use Illuminate\Database\Seeder;

class ConductorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $personas = [
            [
                'nombres' => 'Juan',
                'paterno' => 'García',
                'materno' => 'López',
                'foto' => 'conductor_1.jpg',
                'ci' => '1234567',
                'celular' => '7123456789',
                'direccion' => 'Av. Principal 123',
                'fecha_nacimiento' => '1990-05-15',
                'estado_persona' => 'ACTIVO',
            ],
            [
                'nombres' => 'María',
                'paterno' => 'Rodríguez',
                'materno' => 'Martínez',
                'foto' => 'conductor_2.jpg',
                'ci' => '1234568',
                'celular' => '7123456790',
                'direccion' => 'Calle 5ta 456',
                'fecha_nacimiento' => '1992-08-20',
                'estado_persona' => 'ACTIVO',
            ],
            [
                'nombres' => 'Carlos',
                'paterno' => 'Morales',
                'materno' => 'Sánchez',
                'foto' => 'conductor_3.jpg',
                'ci' => '1234569',
                'celular' => '7123456791',
                'direccion' => 'Pasaje 2 789',
                'fecha_nacimiento' => '1988-12-10',
                'estado_persona' => 'ACTIVO',
            ],
            [
                'nombres' => 'Ana',
                'paterno' => 'Flores',
                'materno' => 'Gutierrez',
                'foto' => 'conductor_4.jpg',
                'ci' => '1234570',
                'celular' => '7123456792',
                'direccion' => 'Boulevard Central 321',
                'fecha_nacimiento' => '1995-03-25',
                'estado_persona' => 'ACTIVO',
            ],
            [
                'nombres' => 'Roberto',
                'paterno' => 'Hernández',
                'materno' => 'Ramos',
                'foto' => 'conductor_5.jpg',
                'ci' => '1234571',
                'celular' => '7123456793',
                'direccion' => 'Avenida Este 654',
                'fecha_nacimiento' => '1985-07-08',
                'estado_persona' => 'ACTIVO',
            ],
        ];

        foreach ($personas as $conductor) {
            $persona = Persona::updateOrCreate(
                ['ci' => $conductor['ci']],
                [
                    'nombres' => $conductor['nombres'],
                    'paterno' => $conductor['paterno'],
                    'materno' => $conductor['materno'],
                    'foto' => $conductor['foto'],
                    'ci' => $conductor['ci'],
                    'celular' => $conductor['celular'],
                    'direccion' => $conductor['direccion'],
                    'fecha_nacimiento' => $conductor['fecha_nacimiento'],
                    'estado_persona' => $conductor['estado_persona'],
                ]
            );

            Conductor::updateOrCreate(
                ['id' => $persona->id],
                [
                    'estado_conductor' => 'ACTIVO',
                ]
            );
        }

        $this->command->info('✓ Conductores creados exitosamente');
    }
}
