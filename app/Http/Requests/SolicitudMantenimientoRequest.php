<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolicitudMantenimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // verificar si el vehiculo esta asignado al conductor mediante el id del usuario que registra
            'id_vehiculo'         => [
                'required',
                Rule::exists('asignacion', 'id_vehiculo')
                    ->where('id_conductor', $this->user()->id_persona)
                    ->where('estado_asignacion', 'ACTIVO')
                    ->where(function ($query) {
                        $query->where('fecha_culminacion', '>=', now())
                            ->orWhereNull('fecha_culminacion');
                    })
            ],
            'tipo_mantenimiento'  => ['required', 'in:PREVENTIVO,CORRECTIVO'],
            'descripcion_problema' => ['required', 'string', 'max:3000'],
            'kilometraje_actual'  => [
                Rule::requiredIf(function () {
                    $vehiculo = \App\Models\Vehiculo::find($this->id_vehiculo);
                    return $vehiculo && $vehiculo->tipo_medicion === 'kilometraje';
                }),
                'nullable',
                'integer',
                'min:0'
            ],
            'horometro_actual'    => [
                Rule::requiredIf(function () {
                    $vehiculo = \App\Models\Vehiculo::find($this->id_vehiculo);
                    return $vehiculo && $vehiculo->tipo_medicion === 'horometro';
                }),
                'nullable',
                'integer',
                'min:0'
            ],
            'observacion'         => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_vehiculo.required'          => 'Debe seleccionar un vehículo.',
            'id_vehiculo.exists'            => 'El vehículo seleccionado no está asignado al conductor o no está activo.',
            'id_conductor.exists'           => 'El conductor seleccionado no existe.',
            'tipo_mantenimiento.required'   => 'Debe indicar el tipo de mantenimiento.',
            'descripcion_problema.required' => 'La descripción del problema o mantenimiento es obligatoria.',
            'fecha_solicitud.required'      => 'La fecha de solicitud es obligatoria.',
        ];
    }
}
