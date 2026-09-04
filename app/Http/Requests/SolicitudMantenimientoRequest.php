<?php

namespace App\Http\Requests;

use App\Models\Vehiculo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolicitudMantenimientoRequest extends FormRequest
{
    /**
     * Registrar (POST) ahora también lo pueden hacer jefe de área y
     * administrador, eligiendo vehículo y conductor desde el formulario (ver
     * SolicitudMantenimientoController::create()); editar (PUT/PATCH) sigue
     * siendo sólo del conductor autor, como antes de este cambio.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        if ($this->isMethod('POST')) {
            return $user->hasAnyRole(['super-admin', 'administrador', 'jefe-area', 'conductor']);
        }

        return $user->hasRole('conductor');
    }

    protected function failedAuthorization(): never
    {
        throw new AuthorizationException('No tienes permiso para registrar o editar esta solicitud de mantenimiento.');
    }

    /**
     * Un conductor que además es jefe de área elige explícitamente el
     * vehículo/conductor (el rol jefe-area prevalece); sólo un conductor
     * "puro" (sin ningún rol de gestión) opera siempre sobre sí mismo, sin
     * elegir nada. Mismo criterio en
     * CreateSolicitudMantenimientoAction::execute() y
     * SolicitudMantenimientoController::esConductorFinal().
     */
    private function esConductorFinal(): bool
    {
        $user = $this->user();

        return $user
            && $user->hasRole('conductor')
            && ! $user->hasAnyRole(['jefe-area', 'administrador', 'super-admin']);
    }

    public function rules(): array
    {

        $esRegistroOffline = $this->is('api/*') && $this->boolean('is_offline');

        // Editar (PUT/PATCH) sigue restringido a conductor (ver authorize()),
        // así que estas reglas ampliadas de vehículo/conductor sólo aplican
        // al registrar (POST) como jefe de área o administrador.
        $eligeVehiculoYConductor = $this->isMethod('POST') && ! $this->esConductorFinal();

        return [
            // verificar si el vehiculo esta asignado al conductor mediante el id del usuario que registra
            'is_offline' => ['nullable', 'boolean'],
            'id_vehiculo' => [
                'required',
                $eligeVehiculoYConductor
                    ? Rule::exists('vehiculo', 'id')->where('estado_vehiculo', 'ACTIVO')
                    : Rule::exists('asignacion', 'id_vehiculo')
                        ->where('id_conductor', $this->user()->id_persona)
                        ->where('estado_asignacion', 'ACTIVO')
                        ->where(function ($query) {
                            $query->where('fecha_culminacion', '>=', now())
                                ->orWhereNull('fecha_culminacion');
                        }),
            ],
            // Sólo se exige/valida cuando el usuario elige vehículo y
            // conductor (ver arriba); en el resto de los casos el conductor
            // se resuelve solo (ignorando cualquier valor recibido, ver
            // CreateSolicitudMantenimientoAction), así que no aplica.
            'id_conductor' => $eligeVehiculoYConductor
                ? [
                    'required',
                    'integer',
                    Rule::exists('asignacion', 'id_conductor')
                        ->where('id_vehiculo', $this->input('id_vehiculo'))
                        ->where(function ($query) {
                            $query->where('estado_asignacion', 'ACTIVO')
                                ->orWhere('estado_asignacion', 'PROVISIONAL');
                        })
                        ->where(function ($query) {
                            $query->whereNull('fecha_culminacion')
                                ->orWhere('fecha_culminacion', '>', now());
                        }),
                ]
                : ['nullable'],
            'tipo_mantenimiento' => ['required', 'in:PREVENTIVO,CORRECTIVO'],
            'descripcion_problema' => ['required', 'string', 'max:3000'],
            // requerir fecha_ si existe campo is_offline y es true
            'fecha_solicitud' => [$esRegistroOffline ? 'required' : 'nullable', 'date'],
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
            'observacion' => ['nullable', 'string', 'max:1500'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_vehiculo.required' => 'Debe seleccionar un vehículo.',
            'id_vehiculo.exists' => 'El vehículo seleccionado no está asignado al conductor o no está activo.',
            'id_conductor.required' => 'Debe seleccionar un conductor.',
            'id_conductor.exists' => 'El conductor seleccionado no está asignado actualmente a este vehículo.',
            'tipo_mantenimiento.required' => 'Debe indicar el tipo de mantenimiento.',
            'descripcion_problema.required' => 'La descripción del problema o mantenimiento es obligatoria.',
            'fecha_solicitud.required' => 'La fecha de solicitud es obligatoria.',
        ];
    }
}
