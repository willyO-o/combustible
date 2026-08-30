<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TipoVehiculoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('tipoVehiculo')?->id;

        return [
            'tipo_vehiculo' => ['required', 'string', 'max:150', Rule::unique('tipo_vehiculo', 'tipo_vehiculo')->ignore($id)],
            'estado_tipo_vehiculo' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
            'id_grupo_vehiculo' => ['required', 'integer', 'exists:grupo_vehiculo,id'],

            // Intervalos de mantenimiento (referencia para futuras alertas): a lo
            // sumo un intervalo por tipo de mantenimiento.
            'intervalos' => ['nullable', 'array'],
            // Sólo tipos de mantenimiento de taller (los de 'operacion_diaria'
            // no aplican a intervalos por tipo de vehículo).
            'intervalos.*.id_tipo_mantenimiento' => [
                'required', 'distinct',
                Rule::exists('tipo_mantenimiento', 'id')->where('ambito', 'taller'),
            ],
            'intervalos.*.tipo_medicion' => ['required', Rule::in(['kilometraje', 'horometro'])],
            'intervalos.*.frecuencia' => ['required', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return [
            'tipo_vehiculo' => 'tipo de vehículo',
            'estado_tipo_vehiculo' => 'estado',
            'id_grupo_vehiculo' => 'grupo de vehículo',
            'intervalos.*.id_tipo_mantenimiento' => 'tipo de mantenimiento',
            'intervalos.*.tipo_medicion' => 'medición',
            'intervalos.*.frecuencia' => 'frecuencia',
        ];
    }

    public function messages(): array
    {
        return [
            'intervalos.*.id_tipo_mantenimiento.distinct' => 'Sólo puede haber un intervalo por tipo de mantenimiento.',
        ];
    }
}
