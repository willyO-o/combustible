<?php

namespace App\Actions\Personas;

use App\Models\Asignacion;
use App\Models\Conductor;
use App\Models\EncargadoArea;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreatePersonaAction
{
    /**
     * Registra una persona y, según los datos recibidos, su cuenta de
     * usuario y los registros de dominio correspondientes a su tipo
     * (conductor + asignación de vehículo, o jefe de área + encargo).
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): Persona
    {
        return DB::transaction(function () use ($data) {
            $persona = Persona::create([
                'ci' => $data['ci'],
                'nombres' => $data['nombres'],
                'paterno' => $data['paterno'] ?? null,
                'materno' => $data['materno'] ?? null,
                'foto' => $data['foto'] ?? null,
                'celular' => $data['celular'] ?? null,
                'direccion' => $data['direccion'] ?? null,
                'fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
                'estado_persona' => $data['estado_persona'],
            ]);

            if (! empty($data['crear_usuario'])) {
                $usuario = User::create([
                    'name' => trim("{$persona->nombres} {$persona->paterno}"),
                    'email' => $data['email'],
                    'password' => Hash::make($persona->ci.'#Plusmetals'),
                    'id_persona' => $persona->id,
                    'estado_usuario' => $data['estado_usuario'] ?? 'ACTIVO',
                ]);

                $this->asignarRol($usuario, $data['tipo']);
            }

            if ($data['tipo'] === 'conductor') {
                Conductor::create([
                    'id' => $persona->id,
                    'estado_conductor' => $data['estado_conductor'] ?? 'ACTIVO',
                ]);

                if (! empty($data['id_vehiculo'])) {
                    Asignacion::create([
                        'id_vehiculo' => $data['id_vehiculo'],
                        'id_conductor' => $persona->id,
                        'fecha_asignacion' => $data['fecha_asignacion'] ?? now(),
                        'estado_asignacion' => 'ACTIVO',
                        'id_usuario' => auth()->id(),
                    ]);
                }
            }

            if ($data['tipo'] === 'jefe-area' && ! empty($data['id_area'])) {
                EncargadoArea::create([
                    'id_persona' => $persona->id,
                    'id_area' => $data['id_area'],
                    'tipo_encargo' => $data['tipo_encargo'] ?? 'TITULAR',
                    'fecha_inicio' => $data['fecha_inicio_encargo'] ?? now(),
                    'motivo' => $data['motivo_encargo'] ?? null,
                    'estado_encargo' => 'ACTIVO',
                ]);
            }

            return $persona;
        });
    }

    private function asignarRol(User $usuario, string $tipo): void
    {
        match ($tipo) {
            'conductor' => $usuario->assignRole('conductor'),
            'jefe-area' => $usuario->assignRole('jefe-area'),
            default => null,
        };
    }
}
