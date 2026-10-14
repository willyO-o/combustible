<?php

namespace App\Http\Controllers;

use App\Actions\Areas\ListHistorialVehiculosAreaAction;
use App\Actions\Areas\ListVehiculosVigentesAreaAction;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     *
     * `usuario` alimenta el formulario editable (sin cambios); `ficha` es
     * sólo lectura: los datos de la persona vinculada, sus roles y lo que
     * implica cada uno (vehículos y licencia si es conductor; áreas a cargo,
     * con sus vehículos vigentes e historial de asignaciones, si es jefe de
     * área — las mismas Actions que la ficha del área, AreaController::show).
     */
    public function edit(
        Request $request,
        ListVehiculosVigentesAreaAction $listVehiculosVigentes,
        ListHistorialVehiculosAreaAction $listHistorialVehiculos,
    ): Response {
        return Inertia::render('Profile/Edit', [
            'usuario' => $request->user()->load('persona'),
            'ficha' => $this->ficha($request->user(), $listVehiculosVigentes, $listHistorialVehiculos),
        ]);
    }

    /**
     * @return array{
     *     roles: array<int, string>,
     *     estado_usuario: ?string,
     *     usuario_desde: ?string,
     *     persona: ?array<string, mixed>,
     *     conductor: ?array{estado: string, licencia: ?array<string, mixed>, vehiculos: array<int, array<string, mixed>>},
     *     areas: array<int, array{id: int, nombre_area: string, tipo_encargo: ?string, desde: ?string, vehiculos: array<int, array<string, mixed>>, historial: array<int, array<string, mixed>>}>
     * }
     */
    private function ficha(
        User $usuario,
        ListVehiculosVigentesAreaAction $listVehiculosVigentes,
        ListHistorialVehiculosAreaAction $listHistorialVehiculos,
    ): array {
        // Consulta aparte (no ->load() sobre $request->user()): si no, estas
        // relaciones también viajarían dentro del prop `usuario`.
        $usuario = User::with([
            'roles',
            'persona.cargo:id,cargo',
            'persona.conductor.asignacionesActivas',
            'persona.encargadoAreas',
        ])->findOrFail($usuario->id);

        $persona = $usuario->persona;
        $conductor = $persona?->conductor;
        $licencia = $conductor?->licenciaConducir();
        // Las fechas de los pivots llegan como texto crudo (pivot genérico).
        $fecha = fn ($valor) => $valor ? Carbon::parse($valor)->format('d/m/Y') : null;

        return [
            'roles' => $usuario->roles->pluck('name')->values()->all(),
            'estado_usuario' => $usuario->estado_usuario,
            'usuario_desde' => $usuario->created_at?->format('d/m/Y'),
            'persona' => $persona ? [
                'ci' => $persona->ci,
                'nombres' => $persona->nombres,
                'paterno' => $persona->paterno,
                'materno' => $persona->materno,
                'nombre_completo' => trim($persona->nombre_completo),
                'fecha_nacimiento' => $persona->f_nacimiento_formatted,
                'edad' => $persona->edad,
                'estado_persona' => $persona->estado_persona,
                'cargo' => $persona->cargo?->cargo,
            ] : null,
            'conductor' => $conductor ? [
                'estado' => $conductor->estado_conductor,
                'licencia' => $licencia ? [
                    'numero' => $licencia->numero_documento,
                    'categoria' => $licencia->categoria,
                    'vencimiento' => $licencia->fecha_vencimiento?->format('d/m/Y'),
                    'vencida' => (bool) $licencia->fecha_vencimiento?->isPast(),
                ] : null,
                'vehiculos' => $conductor->asignacionesActivas->map(fn ($vehiculo) => [
                    'id' => $vehiculo->id,
                    'codigo' => $vehiculo->codigo,
                    'nro_placa' => $vehiculo->nro_placa,
                    'descripcion' => trim("{$vehiculo->marca} {$vehiculo->modelo}"),
                    'anio' => $vehiculo->anio,
                    'estado_asignacion' => $vehiculo->pivot->estado_asignacion,
                    'desde' => $fecha($vehiculo->pivot->fecha_asignacion),
                ])->values()->all(),
            ] : null,
            'areas' => ($persona?->encargadoAreas ?? collect())->map(fn ($area) => [
                'id' => $area->id,
                'nombre_area' => $area->nombre_area,
                'tipo_encargo' => $area->pivot->tipo_encargo,
                'desde' => $fecha($area->pivot->fecha_inicio),
                'vehiculos' => $listVehiculosVigentes->execute($area)->values()->all(),
                'historial' => $listHistorialVehiculos->execute($area)->values()->all(),
            ])->values()->all(),
        ];
    }

    /**
     * Update the user's profile information (foto, nombre, correo, celular,
     * dirección). No gestiona el nombre de la persona ni permite eliminar la
     * cuenta: eso se hace desde el módulo de Personas/Usuarios (administradores).
     *
     * `email` sí es editable; ProfileUpdateRequest exige `current_password`
     * cuando cambia (es la credencial de acceso). La contraseña sólo se usa
     * para validar, nunca se guarda. El cambio queda en la auditoría de User.
     *
     * `celular`/`direccion` se guardan en persona a través de la relación
     * users.id_persona -> persona (no existen columnas propias en users): si
     * el usuario no tiene una persona vinculada (ej. cuentas de sistema),
     * esos campos simplemente no se actualizan.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $usuario = $request->user();

        // Antes de guardar: después, el usuario ya tiene el correo nuevo.
        $cambiaElCorreo = $request->cambiaElCorreo();

        $data = $request->validated();
        unset($data['current_password']);

        $datosPersona = [
            'celular' => $data['celular'] ?? null,
            'direccion' => $data['direccion'] ?? null,
        ];
        unset($data['celular'], $data['direccion']);

        if ($request->hasFile('foto')) {
            if ($usuario->foto) {
                Storage::disk('public')->delete($usuario->foto);
            }
            $data['foto'] = $request->file('foto')->store('usuarios', 'public');
        } else {
            unset($data['foto']);
        }

        $usuario->fill($data)->save();

        if ($usuario->persona) {
            $usuario->persona->update($datosPersona);
        }

        return redirect()->route('profile.edit')
            ->with('success', $cambiaElCorreo
                ? 'Perfil actualizado. Desde ahora inicia sesión con '.$usuario->email.'.'
                : 'Perfil actualizado exitosamente.');
    }
}
