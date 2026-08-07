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
                'foto' => 'conductores/EmtgAtJKBQlpLmnxjY7W4VLw91eXG4Ct6e97IARI.jpg',
                'ci' => '1234567',
                'celular' => '77777777',
                'direccion' => 'Av. Principal 123',
                'fecha_nacimiento' => '1990-05-15',
                'estado_persona' => 'ACTIVO',
                'es_conductor' => true,
            ],
            [
                'nombres' => 'María',
                'paterno' => 'Rodríguez',
                'materno' => 'Martínez',
                'foto' => 'conductores/EmtgAtJKBQlpLmnxjY7W4VLw91eXG4Ct6e97IARI.jpg',
                'ci' => '1234568',
                'celular' => '77777778',
                'direccion' => 'Calle 5ta 456',
                'fecha_nacimiento' => '1992-08-20',
                'estado_persona' => 'ACTIVO',
                'es_conductor' => true,

            ],
            [
                'nombres' => 'Carlos',
                'paterno' => 'Morales',
                'materno' => 'Sánchez',
                'foto' => 'conductores/EmtgAtJKBQlpLmnxjY7W4VLw91eXG4Ct6e97IARI.jpg',
                'ci' => '1234569',
                'celular' => '77777779',
                'direccion' => 'Pasaje 2 789',
                'fecha_nacimiento' => '1988-12-10',
                'estado_persona' => 'ACTIVO',
                'es_conductor' => true,

            ],
            [
                'nombres' => 'Ana',
                'paterno' => 'Flores',
                'materno' => 'Gutierrez',
                'foto' => 'conductores/EmtgAtJKBQlpLmnxjY7W4VLw91eXG4Ct6e97IARI.jpg',
                'ci' => '1234570',
                'celular' => '77777780',
                'direccion' => 'Boulevard Central 321',
                'fecha_nacimiento' => '1995-03-25',
                'estado_persona' => 'ACTIVO',
                'es_conductor' => true,

            ],
            [
                'nombres' => 'Jose Luis',
                'paterno' => 'Pérez',
                'materno' => 'Ramos',
                'foto' => 'conductores/EmtgAtJKBQlpLmnxjY7W4VLw91eXG4Ct6e97IARI.jpg',
                'ci' => '1234572',
                'celular' => '77777781',
                'direccion' => 'Avenida Este 654',
                'fecha_nacimiento' => '1985-07-08',
                'estado_persona' => 'ACTIVO',
                'es_conductor' => false,

            ],
            [
                'nombres' => 'Carlos',
                'paterno' => 'Cruz',
                'materno' => 'Ramos',
                'foto' => 'conductores/EmtgAtJKBQlpLmnxjY7W4VLw91eXG4Ct6e97IARI.jpg',
                'ci' => '1234573',
                'celular' => '77777782',
                'direccion' => 'Avenida Este 654',
                'fecha_nacimiento' => '1985-07-08',
                'estado_persona' => 'ACTIVO',
                'es_conductor' => false,

            ],
            [
                'nombres' => 'Maria Luisa',
                'paterno' => 'Jiménez',
                'materno' => 'Ramos',
                'foto' => 'conductores/EmtgAtJKBQlpLmnxjY7W4VLw91eXG4Ct6e97IARI.jpg',
                'ci' => '1234574',
                'celular' => '77777783',
                'direccion' => 'Avenida Este 654',
                'fecha_nacimiento' => '1985-07-08',
                'estado_persona' => 'ACTIVO',
                'es_conductor' => false,

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

            if ($conductor['es_conductor']) {
                Conductor::updateOrCreate(
                    ['id' => $persona->id],
                    [
                        'estado_conductor' => 'ACTIVO',
                    ]
                );
            }

        }

        $this->command->info('✓ Conductores creados exitosamente');
    }
}
