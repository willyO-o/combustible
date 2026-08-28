<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class EjecucionOrdenTrabajoRequest extends FormRequest
{
    /**
     * Un jefe de área/administrador puede culminar la ejecución de cualquier
     * orden; un técnico de mantenimiento sólo la de sus propias órdenes
     * asignadas (id_usuario_ejecuta).
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        if ($user->hasAnyRole(['super-admin', 'administrador', 'jefe-area'])) {
            return true;
        }

        $orden = $this->route('orden');

        return $user->hasRole('tecnico-mantenimiento')
            && $orden?->id_usuario_ejecuta === $user->id;
    }

    protected function failedAuthorization(): never
    {
        throw new AuthorizationException('Sólo puede culminar la ejecución de las órdenes que tiene asignadas.');
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->expectsJson()) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Los datos enviados no son válidos.',
                'errors' => $validator->errors(),
            ], 422));
        }

        parent::failedValidation($validator);
    }

    public function rules(): array
    {
        // fecha_ejecucion ya debería estar registrada desde que se marcó EN_EJECUCION
        // (cambiarEstado) y fecha_culminacion se fija automáticamente al culminar:
        // ninguna de las dos es un campo del formulario.
        // El km/horómetro final a pedir depende del tipo de medición del vehículo.
        $tipoMedicion = $this->route('orden')?->vehiculo?->tipo_medicion;

        return [
            'kilometraje_actual' => [
                Rule::requiredIf($tipoMedicion === 'kilometraje'),
                'nullable',
                'integer',
                'min:0',
            ],
            'horometro_actual' => [
                Rule::requiredIf($tipoMedicion === 'horometro'),
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
            'kilometraje_actual.required' => 'El kilometraje actual es obligatorio.',
            'horometro_actual.required' => 'El horómetro actual es obligatorio.',
        ];
    }
}
