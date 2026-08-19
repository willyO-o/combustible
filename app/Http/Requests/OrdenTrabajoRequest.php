<?php

namespace App\Http\Requests;

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
            'id_conductor' => ['nullable', 'exists:conductor,id'],
            'id_solicitud_mantenimiento' => ['nullable', 'exists:solicitud_mantenimiento,id'],
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
