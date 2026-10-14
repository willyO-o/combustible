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
     * usuario y los registros de dominio de cada rol elegido (una persona
     * puede tener varios a la vez): conductor -> conductor + asignación de
     * vehículo; jefe-area -> encargo de área. El resto de roles
     * (administrador, técnico de mantenimiento, ...) sólo se asignan a la
     * cuenta de usuario.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): Persona
    {
        return DB::transaction(function () use ($data) {
            $roles = $data['roles'] ?? [];

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
                    'password' => Hash::make(User::contrasenaInicial($data['password'] ?? null, $persona->ci)),
                    'id_persona' => $persona->id,
                    'estado_usuario' => $data['estado_usuario'] ?? 'ACTIVO',
                ]);

                $usuario->syncRoles($roles);
            }

            if (in_array('conductor', $roles, true)) {
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

            if (in_array('jefe-area', $roles, true) && ! empty($data['id_area'])) {
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
}
