<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SolicitudMantenimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_vehiculo'         => ['required', 'exists:vehiculo,id'],
            'id_conductor'        => ['nullable', 'exists:conductor,id'],
            'tipo_mantenimiento'  => ['required', 'in:PREVENTIVO,CORRECTIVO'],
            'descripcion_problema'=> ['required', 'string', 'max:1000'],
            'kilometraje_actual'  => ['nullable', 'integer', 'min:0'],
            'fecha_solicitud'     => ['required', 'date'],
            'observacion'         => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_vehiculo.required'          => 'Debe seleccionar un vehículo.',
            'id_vehiculo.exists'            => 'El vehículo seleccionado no existe.',
            'id_conductor.exists'           => 'El conductor seleccionado no existe.',
            'tipo_mantenimiento.required'   => 'Debe indicar el tipo de mantenimiento.',
            'descripcion_problema.required' => 'La descripción del problema o mantenimiento es obligatoria.',
            'fecha_solicitud.required'      => 'La fecha de solicitud es obligatoria.',
        ];
    }
}
