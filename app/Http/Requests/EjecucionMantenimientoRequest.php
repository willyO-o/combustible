<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EjecucionMantenimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_inicio'                => ['required', 'date'],
            'fecha_fin'                   => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'kilometraje_al_mantenimiento'=> ['nullable', 'integer', 'min:0'],
            'trabajo_realizado'           => ['required', 'string', 'max:2000'],
            'costo_mano_obra'             => ['nullable', 'numeric', 'min:0'],
            'costo_total'                 => ['nullable', 'numeric', 'min:0'],
            'observacion'                 => ['nullable', 'string', 'max:500'],

            // Ítem de repuestos / insumos utilizados
            'items'                       => ['nullable', 'array'],
            'items.*.tipo_item'           => ['required_with:items', 'in:REPUESTO,ACEITE,LLANTA,INSUMO,OTRO'],
            'items.*.id_repuesto'         => ['nullable', 'exists:repuesto,id'],
            'items.*.nombre_item'         => ['nullable', 'string', 'max:200'],
            'items.*.unidad_medida'       => ['required_with:items', 'in:UNIDAD,LITRO,KILOGRAMO,METRO,JUEGO,CAJA,BOLSA,PAQUETE'],
            'items.*.cantidad_utilizada'  => ['required_with:items', 'numeric', 'min:0.01'],
            'items.*.costo_unitario'      => ['required_with:items', 'numeric', 'min:0'],
            'items.*.subtotal'            => ['required_with:items', 'numeric', 'min:0'],
            'items.*.observacion'         => ['nullable', 'string', 'max:300'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_inicio.required'             => 'La fecha de inicio es obligatoria.',
            'fecha_fin.required'                => 'La fecha de fin es obligatoria.',
            'fecha_fin.after_or_equal'          => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'trabajo_realizado.required'        => 'Debe describir el trabajo realizado.',
            'items.*.tipo_item.required_with'   => 'Cada ítem debe tener tipo.',
            'items.*.cantidad_utilizada.required_with' => 'La cantidad es obligatoria.',
            'items.*.costo_unitario.required_with'     => 'El costo unitario es obligatorio.',
        ];
    }
}
