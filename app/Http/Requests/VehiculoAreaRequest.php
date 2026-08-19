<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehiculoAreaRequest extends FormRequest
{
    /**
     * Sólo un jefe de área o administrador puede reasignar un vehículo a
     * otra área.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['super-admin', 'administrador', 'jefe-area']) ?? false;
    }

    protected function failedAuthorization(): never
    {
        throw new AuthorizationException('Sólo un jefe de área o administrador puede reasignar vehículos a un área.');
    }

    public function rules(): array
    {
        return [
            'id_area' => ['required', 'exists:area,id'],
            'estado_asignacion' => ['required', Rule::in(['ACTIVO', 'PROVISIONAL'])],
            'fecha_culminacion' => ['nullable', 'date', 'after_or_equal:today', 'prohibited_unless:estado_asignacion,PROVISIONAL'],
            'motivo_asignacion' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'id_area' => 'área',
            'estado_asignacion' => 'tipo de asignación',
            'fecha_culminacion' => 'fecha de finalización',
            'motivo_asignacion' => 'motivo',
        ];
    }

    public function messages(): array
    {
        return [
            'id_area.required' => 'Debe seleccionar un área.',
            'fecha_culminacion.prohibited_unless' => 'La fecha de finalización sólo aplica para asignaciones provisionales.',
        ];
    }
}
