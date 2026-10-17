<?php

namespace App\Http\Requests;

use App\Models\Vehiculo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolicitudMantenimientoRequest extends FormRequest
{
    /**
     * Registrar (POST) lo puede hacer cualquier usuario con el permiso
     * mantenimiento.solicitudes.crear (conductor, jefe de área,
     * administrador o un rol personalizado), eligiendo vehículo y conductor
     * desde el formulario salvo el conductor puro (ver
     * SolicitudMantenimientoController::create()); editar (PUT/PATCH) sigue
     * siendo sólo del conductor autor.
     *
     * Permiso con guard 'web' explícito y checkPermissionTo(): bajo auth:api
     * el guard por defecto es 'api' y los permisos viven en 'web' (ver
     * .ai/rules/v1.md).
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        if ($this->isMethod('POST')) {
            return $user->hasRole('super-admin')
                || $user->checkPermissionTo('mantenimiento.solicitudes.crear', 'web');
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
        return (bool) $this->user()?->esConductorPuro();
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
            // conductor (ver arriba): cualquier operario ACTIVO, no sólo los
            // asignados al vehículo (a pedido del usuario, la asignación no
            // es confiable). En el resto de los casos el conductor se resuelve
            // solo (ignorando cualquier valor recibido, ver
            // CreateSolicitudMantenimientoAction), así que no aplica. Un
            // registro offline pudo capturarse con un operario que después se
            // inactivó: ahí basta con que exista.
            'id_conductor' => $eligeVehiculoYConductor
                ? [
                    'required',
                    'integer',
                    $esRegistroOffline
                        ? Rule::exists('conductor', 'id')
                        : Rule::exists('conductor', 'id')->where('estado_conductor', 'ACTIVO')->whereNull('deleted_at'),
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
            'id_conductor.exists' => 'El conductor seleccionado no existe o no está activo.',
            'tipo_mantenimiento.required' => 'Debe indicar el tipo de mantenimiento.',
            'descripcion_problema.required' => 'La descripción del problema o mantenimiento es obligatoria.',
            'fecha_solicitud.required' => 'La fecha de solicitud es obligatoria.',
        ];
    }
}
