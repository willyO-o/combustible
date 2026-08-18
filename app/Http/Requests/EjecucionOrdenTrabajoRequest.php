<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EjecucionOrdenTrabajoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_ejecucion' => ['nullable', 'date'],
            'fecha_culminacion' => ['required', 'date', 'after_or_equal:fecha_ejecucion'],
            'kilometraje_actual' => ['nullable', 'integer', 'min:0'],
            'horometro_actual' => ['nullable', 'integer', 'min:0'],
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
            'fecha_culminacion.required' => 'La fecha de culminación es obligatoria.',
            'fecha_culminacion.after_or_equal' => 'La fecha de culminación debe ser igual o posterior a la de ejecución.',
            'detalles.required' => 'Debe registrar al menos un ítem del trabajo realizado.',
            'detalles.*.id_tipo_mantenimiento.required' => 'Cada ítem debe indicar el tipo de mantenimiento.',
            'detalles.*.cantidad.required' => 'La cantidad es obligatoria.',
            'detalles.*.costo_unitario.required' => 'El costo unitario es obligatorio.',
        ];
    }
}
