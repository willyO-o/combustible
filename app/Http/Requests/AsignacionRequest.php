<?php

namespace App\Http\Requests;

use App\Models\Vehiculo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AsignacionRequest extends FormRequest
{
    /**
     * Sólo un jefe de área o administrador puede reasignar vehículos a un
     * conductor.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['super-admin', 'administrador', 'jefe-area']) ?? false;
    }

    protected function failedAuthorization(): never
    {
        throw new AuthorizationException('Sólo un jefe de área o administrador puede reasignar vehículos.');
    }

    public function rules(): array
    {
        return [
            'id_vehiculo' => ['required', 'exists:vehiculo,id'],
            'estado_asignacion' => ['required', Rule::in(['ACTIVO', 'PROVISIONAL'])],
            'fecha_culminacion' => ['nullable', 'date', 'after_or_equal:today', 'prohibited_unless:estado_asignacion,PROVISIONAL'],
            'detalle' => ['nullable', 'string', 'max:1000'],
            'kilometraje_inicial' => [
                Rule::requiredIf(function () {
                    $vehiculo = Vehiculo::find($this->id_vehiculo);

                    return $vehiculo && $vehiculo->tipo_medicion === 'kilometraje';
                }),
                'nullable',
                'numeric',
                'min:0',
            ],
            'horometro_inicial' => [
                Rule::requiredIf(function () {
                    $vehiculo = Vehiculo::find($this->id_vehiculo);

                    return $vehiculo && $vehiculo->tipo_medicion === 'horometro';
                }),
                'nullable',
                'numeric',
                'min:0',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'id_vehiculo' => 'vehículo',
            'estado_asignacion' => 'tipo de asignación',
            'fecha_culminacion' => 'fecha de finalización',
            'detalle' => 'motivo',
            'kilometraje_inicial' => 'kilometraje inicial',
            'horometro_inicial' => 'horómetro inicial',
        ];
    }

    public function messages(): array
    {
        return [
            'id_vehiculo.required' => 'Debe seleccionar un vehículo.',
            'kilometraje_inicial.required' => 'El kilometraje inicial es obligatorio.',
            'horometro_inicial.required' => 'El horómetro inicial es obligatorio.',
            'fecha_culminacion.prohibited_unless' => 'La fecha de finalización sólo aplica para asignaciones provisionales.',
        ];
    }
}
