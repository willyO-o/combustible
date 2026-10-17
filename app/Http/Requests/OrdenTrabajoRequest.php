<?php

namespace App\Http\Requests;

use App\Models\SolicitudMantenimiento;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrdenTrabajoRequest extends FormRequest
{
    /**
     * Generar (POST) u editar (PUT) una orden lo puede cualquier usuario con
     * el permiso mantenimiento.ordenes.crear / .editar respectivamente (el
     * mismo que exige la ruta; super-admin pasa por Gate::before).
     */
    public function authorize(): bool
    {
        $permiso = $this->isMethod('POST') ? 'mantenimiento.ordenes.crear' : 'mantenimiento.ordenes.editar';

        return (bool) $this->user()?->can($permiso);
    }

    protected function failedAuthorization(): never
    {
        throw new AuthorizationException('No tiene permiso para generar o editar una orden de trabajo.');
    }

    public function rules(): array
    {
        return [
            'id_vehiculo' => ['required', 'exists:vehiculo,id'],
            // Opcional. Elegido manualmente junto al vehículo (sin solicitud de
            // origen): cualquier operario ACTIVO, no sólo los asignados al
            // vehículo (a pedido del usuario, la asignación no es confiable).
            // Con solicitud de origen el conductor llega heredado de ella (ver
            // CreateOrdenTrabajoAction) y basta con que exista: pudo cambiar de
            // asignación o inactivarse desde que se generó la solicitud.
            'id_conductor' => [
                'nullable',
                'integer',
                $this->filled('id_solicitud_mantenimiento')
                    ? Rule::exists('conductor', 'id')
                    : Rule::exists('conductor', 'id')->where('estado_conductor', 'ACTIVO')->whereNull('deleted_at'),
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
            'id_conductor.exists' => 'El conductor seleccionado no existe o no está activo.',
            'id_usuario_ejecuta.required' => 'Debe asignar un responsable de ejecución.',
            'tipo_mantenimiento.required' => 'Debe indicar si es preventivo o correctivo.',
        ];
    }
}
