<?php

namespace App\Http\Requests;

use App\Models\Asignacion;
use App\Models\SolicitudMantenimiento;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrdenTrabajoRequest extends FormRequest
{
    /**
     * Sólo un jefe de área o administrador puede generar o editar una orden
     * de trabajo.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['super-admin', 'administrador', 'jefe-area']) ?? false;
    }

    protected function failedAuthorization(): never
    {
        throw new AuthorizationException('Sólo un jefe de área o administrador puede generar o editar una orden de trabajo.');
    }

    public function rules(): array
    {
        return [
            'id_vehiculo' => ['required', 'exists:vehiculo,id'],
            'id_conductor' => [
                'nullable',
                'exists:conductor,id',
                function ($attribute, $value, $fail) {
                    // Cuando la orden nace de una solicitud de origen, el
                    // conductor llega heredado de esa solicitud (ver
                    // store()) y no se re-valida aquí: podría haber cambiado
                    // de asignación desde que se generó la solicitud, y esa
                    // no es razón para bloquear la orden.
                    if (! $value || $this->filled('id_solicitud_mantenimiento')) {
                        return;
                    }

                    // Elegido manualmente junto al vehículo (sin solicitud de
                    // origen): debe ser un conductor realmente asignado a ese
                    // vehículo, resuelto en una sola consulta (exists).
                    $asignado = Asignacion::where('id_vehiculo', $this->input('id_vehiculo'))
                        ->where('id_conductor', $value)
                        ->where(function ($query) {
                            $query->where('estado_asignacion', 'ACTIVO')
                                ->orWhere('estado_asignacion', 'PROVISIONAL');
                        })
                        ->where(function ($query) {
                            $query->whereNull('fecha_culminacion')
                                ->orWhere('fecha_culminacion', '>', now());
                        })
                        ->exists();

                    if (! $asignado) {
                        $fail('El conductor seleccionado no está asignado actualmente a este vehículo.');
                    }
                },
            ],
            'id_solicitud_mantenimiento' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if (! $value) {
                        return;
                    }

                    $ordenActual = $this->route('orden');

                    // Al editar, la propia orden ya trae esta solicitud enganchada
                    // (estado APROBADA y con esta misma orden) — no se re-valida
                    // en ese caso, sólo si se intenta enganchar OTRA solicitud.
                    if ($ordenActual && (int) $ordenActual->id_solicitud_mantenimiento === (int) $value) {
                        return;
                    }

                    // Existencia + estado + "sin orden ya asignada" en UNA sola
                    // consulta (whereDoesntHave -> NOT EXISTS), resuelta en la
                    // BD en vez de 3 idas y vueltas (exists + find + exists).
                    $disponible = SolicitudMantenimiento::where('id', $value)
                        ->where('estado', 'PENDIENTE')
                        ->whereDoesntHave('ordenTrabajo')
                        ->exists();

                    if (! $disponible) {
                        $fail('Esta solicitud de mantenimiento ya no está disponible: no existe, no está pendiente o ya tiene una orden de trabajo asignada.');
                    }
                },
            ],
            'id_taller' => ['nullable', 'exists:taller,id'],
            'id_usuario_ejecuta' => [
                'required',
                Rule::exists('users', 'id'),
                function ($attribute, $value, $fail) {
                    $usuario = User::find($value);

                    if (! $usuario || ! $usuario->hasRole('tecnico-mantenimiento')) {
                        $fail('El responsable de ejecución debe ser un usuario con el rol de técnico de mantenimiento.');
                    }
                },
            ],
            'tipo_mantenimiento' => ['required', 'in:PREVENTIVO,CORRECTIVO'],
            'nota_emisor' => ['nullable', 'string', 'max:1000'],
            'kilometraje_actual' => [
                Rule::requiredIf(function () {
                    $vehiculo = Vehiculo::find($this->id_vehiculo);

                    return $vehiculo && $vehiculo->tipo_medicion === 'kilometraje';
                }),
                'nullable',
                'integer',
                'min:0',
            ],
            'horometro_actual' => [
                Rule::requiredIf(function () {
                    $vehiculo = Vehiculo::find($this->id_vehiculo);

                    return $vehiculo && $vehiculo->tipo_medicion === 'horometro';
                }),
                'nullable',
                'integer',
                'min:0',
            ],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_vehiculo.required' => 'Debe seleccionar un vehículo.',
            'id_usuario_ejecuta.required' => 'Debe asignar un responsable de ejecución.',
            'tipo_mantenimiento.required' => 'Debe indicar si es preventivo o correctivo.',
        ];
    }
}
