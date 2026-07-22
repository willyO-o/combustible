<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlanMantenimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_vehiculo'                 => ['required', 'exists:vehiculo,id'],
            'id_tipo_mantenimiento'       => ['required', 'exists:tipo_mantenimiento,id'],
            'id_solicitud_mantenimiento'  => ['nullable', 'exists:solicitud_mantenimiento,id'],
            'id_taller'                   => ['nullable', 'exists:taller,id'],
            'tipo_mantenimiento'          => ['required', 'in:PREVENTIVO,CORRECTIVO'],
            'tipo_orden'                  => ['required', 'in:INTERNO,EXTERNO'],
            'descripcion_trabajo_ordenado'=> ['nullable', 'string', 'max:1000'],
            'fecha_orden'                 => ['nullable', 'date'],
            'kilometraje_programado'      => ['nullable', 'integer', 'min:0'],
            'fecha_programada'            => ['nullable', 'date'],
            'frecuencia_km'               => ['nullable', 'integer', 'min:0'],
            'frecuencia_mes'              => ['nullable', 'integer', 'min:0'],
            'observacion'                 => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_vehiculo.required'           => 'Debe seleccionar un vehículo.',
            'id_tipo_mantenimiento.required' => 'Debe seleccionar el tipo de mantenimiento.',
            'tipo_mantenimiento.required'    => 'Debe indicar si es preventivo o correctivo.',
            'tipo_orden.required'            => 'Debe indicar si la orden es interna o externa.',
        ];
    }
}
