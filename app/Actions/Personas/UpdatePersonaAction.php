<?php

namespace App\Actions\Personas;

use App\Models\Asignacion;
use App\Models\Conductor;
use App\Models\EncargadoArea;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UpdatePersonaAction
{
    /**
     * Actualiza una persona y sincroniza su cuenta de usuario y sus
     * registros de conductor/jefe de área según el tipo recibido,
     * cerrando los registros del tipo anterior si cambió.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(Persona $persona, array $data): Persona
    {
        return DB::transaction(function () use ($persona, $data) {
            $tipoAnterior = $persona->tipo_actual;

            $persona->update([
                'ci' => $data['ci'],
                'nombres' => $data['nombres'],
                'paterno' => $data['paterno'] ?? null,
                'materno' => $data['materno'] ?? null,
                'foto' => $data['foto'] ?? $persona->foto,
                'celular' => $data['celular'] ?? null,
                'direccion' => $data['direccion'] ?? null,
                'fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
                'estado_persona' => $data['estado_persona'],
            ]);

            $this->sincronizarUsuario($persona, $data);

            if ($tipoAnterior === 'conductor' && $data['tipo'] !== 'conductor') {
                $this->cerrarAsignacionesActivas($persona);
            }

            if ($data['tipo'] === 'conductor') {
                $this->sincronizarConductor($persona, $data);
            }

            if ($tipoAnterior === 'jefe-area' && $data['tipo'] !== 'jefe-area') {
                $this->cerrarEncargoActivo($persona);
            }

            if ($data['tipo'] === 'jefe-area' && ! empty($data['id_area'])) {
                $this->sincronizarEncargoArea($persona, $data);
            }

            return $persona->fresh();
        });
    }

    private function sincronizarUsuario(Persona $persona, array $data): void
    {
        if (empty($data['crear_usuario'])) {
            return;
        }

        $usuario = $persona->user;

        if (! $usuario) {
            $usuario = User::create([
                'name' => trim("{$persona->nombres} {$persona->paterno}"),
                'email' => $data['email'],
                'password' => Hash::make($persona->ci.'#Plusmetals'),
                'id_persona' => $persona->id,
                'estado_usuario' => $data['estado_usuario'] ?? 'ACTIVO',
            ]);
        } else {
            $usuario->update([
                'email' => $data['email'],
                'estado_usuario' => $data['estado_usuario'] ?? 'ACTIVO',
            ]);
        }

        $usuario->syncRoles(array_filter([
            match ($data['tipo']) {
                'conductor' => 'conductor',
                'jefe-area' => 'jefe-area',
                default => null,
            },
        ]));
    }

    private function cerrarAsignacionesActivas(Persona $persona): void
    {
        Asignacion::where('id_conductor', $persona->id)
            ->whereIn('estado_asignacion', ['ACTIVO', 'PROVISIONAL'])
            ->update([
                'estado_asignacion' => 'INACTIVO',
                'fecha_culminacion' => now(),
            ]);
    }

    private function sincronizarConductor(Persona $persona, array $data): void
    {
        $conductor = Conductor::firstOrCreate(
            ['id' => $persona->id],
            ['estado_conductor' => $data['estado_conductor'] ?? 'ACTIVO']
        );

        $conductor->update(['estado_conductor' => $data['estado_conductor'] ?? 'ACTIVO']);

        if (empty($data['id_vehiculo'])) {
            return;
        }

        $asignacionActiva = Asignacion::where('id_conductor', $persona->id)
            ->where('estado_asignacion', 'ACTIVO')
            ->first();

        if ($asignacionActiva && (int) $asignacionActiva->id_vehiculo === (int) $data['id_vehiculo']) {
            return; // mismo vehículo, nada que reasignar
        }

        if ($asignacionActiva) {
            $asignacionActiva->update([
                'estado_asignacion' => 'REASIGNADO',
                'fecha_culminacion' => now(),
            ]);
        }

        Asignacion::create([
            'id_vehiculo' => $data['id_vehiculo'],
            'id_conductor' => $persona->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'ACTIVO',
            'id_usuario' => auth()->id(),
        ]);
    }

    private function cerrarEncargoActivo(Persona $persona): void
    {
        EncargadoArea::where('id_persona', $persona->id)
            ->where('estado_encargo', 'ACTIVO')
            ->update([
                'estado_encargo' => 'INACTIVO',
                'fecha_fin' => now(),
            ]);
    }

    private function sincronizarEncargoArea(Persona $persona, array $data): void
    {
        $encargoActivo = EncargadoArea::where('id_persona', $persona->id)
            ->where('estado_encargo', 'ACTIVO')
            ->first();

        if ($encargoActivo && (int) $encargoActivo->id_area === (int) $data['id_area']) {
            return; // misma área, nada que reasignar
        }

        if ($encargoActivo) {
            $encargoActivo->update([
                'estado_encargo' => 'INACTIVO',
                'fecha_fin' => now(),
            ]);
        }

        EncargadoArea::create([
            'id_persona' => $persona->id,
            'id_area' => $data['id_area'],
            'tipo_encargo' => $data['tipo_encargo'] ?? 'TITULAR',
            'fecha_inicio' => $data['fecha_inicio_encargo'] ?? now(),
            'motivo' => $data['motivo_encargo'] ?? null,
            'estado_encargo' => 'ACTIVO',
        ]);
    }
}
