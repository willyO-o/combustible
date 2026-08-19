<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EjecucionOrdenTrabajoRequest extends FormRequest
{
    /**
     * Un jefe de área/administrador puede registrar la ejecución de cualquier
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
        throw new AuthorizationException('Sólo puede registrar la ejecución de las órdenes que tiene asignadas.');
    }

    public function rules(): array
    {
        // fecha_ejecucion se registra al marcar la orden EN_EJECUCION (cambiarEstado) y
        // fecha_culminacion se fija automáticamente al registrar esta ejecución: ninguna
        // de las dos es un campo del formulario.
        // El km/horómetro a pedir depende del tipo de medición del vehículo de la orden.
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

            // Detalle de repuestos / insumos / mano de obra aplicados
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.id_tipo_mantenimiento' => ['required', 'exists:tipo_mantenimiento,id'],
            'detalles.*.id_repuesto' => ['nullable', 'exists:repuesto,id'],
            'detalles.*.detalle' => ['nullable', 'string', 'max:255'],
            'detalles.*.cantidad' => ['required', 'integer', 'min:1'],
            'detalles.*.costo_unitario' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'kilometraje_actual.required' => 'El kilometraje actual es obligatorio.',
            'horometro_actual.required' => 'El horómetro actual es obligatorio.',
            'detalles.required' => 'Debe registrar al menos un ítem del trabajo realizado.',
            'detalles.*.id_tipo_mantenimiento.required' => 'Cada ítem debe indicar el tipo de mantenimiento.',
            'detalles.*.cantidad.required' => 'La cantidad es obligatoria.',
            'detalles.*.costo_unitario.required' => 'El costo unitario es obligatorio.',
        ];
    }
}
