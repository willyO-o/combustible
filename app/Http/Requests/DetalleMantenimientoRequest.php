<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DetalleMantenimientoRequest extends FormRequest
{
    /**
     * Un jefe de área/administrador puede registrar/editar el detalle de
     * cualquier orden; un técnico de mantenimiento sólo el de sus propias
     * órdenes asignadas (id_usuario_ejecuta). Mismo criterio que
     * EjecucionOrdenTrabajoRequest.
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
        throw new AuthorizationException('Sólo puede registrar el detalle de las órdenes que tiene asignadas.');
    }

    public function rules(): array
    {
        // El horómetro/kilometraje a pedir depende del tipo de medición del
        // vehículo de la orden (mismo criterio que OrdenTrabajoRequest y
        // EjecucionOrdenTrabajoRequest).
        $tipoMedicion = $this->route('orden')?->vehiculo?->tipo_medicion;

        return [
            'id_tipo_mantenimiento' => ['required', 'exists:tipo_mantenimiento,id'],
            'id_repuesto' => ['nullable', 'exists:repuesto,id'],
            'fecha' => ['required', 'date'],
            'horometro' => [
                Rule::requiredIf($tipoMedicion === 'horometro'),
                'nullable',
                'numeric',
                'min:0',
            ],
            'kilometraje' => [
                Rule::requiredIf($tipoMedicion === 'kilometraje'),
                'nullable',
                'numeric',
                'min:0',
            ],
            'cantidad' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_tipo_mantenimiento.required' => 'Debe indicar el tipo de mantenimiento.',
            'fecha.required' => 'La fecha es obligatoria.',
            'horometro.required' => 'El horómetro es obligatorio.',
            'kilometraje.required' => 'El kilometraje es obligatorio.',
            'cantidad.required' => 'La cantidad es obligatoria.',
        ];
    }

    public function attributes(): array
    {
        return [
            'id_tipo_mantenimiento' => 'tipo de mantenimiento',
            'id_repuesto' => 'repuesto',
            'fecha' => 'fecha',
            'horometro' => 'horómetro',
            'kilometraje' => 'kilometraje',
            'cantidad' => 'cantidad',
        ];
    }
}
