<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehiculoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $vehiculo = $this->route('vehiculo');

        return [
            'nro_placa'           => ['required', 'string', 'max:20', Rule::unique('vehiculo', 'nro_placa')->ignore($vehiculo?->id)->whereNull('deleted_at')],
            'anio'                => ['nullable', 'string', 'max:4', 'regex:/^\d{4}$/'],
            'marca'               => ['nullable', 'string', 'max:50'],
            'estado_vehiculo'     => ['required', Rule::in(['ACTIVO', 'RETIRADO', 'VENDIDO'])],
            'id_tipo_combustible' => ['required', 'integer', 'exists:tipo_combustible,id'],
            'id_tipo_vehiculo'    => ['required', 'integer', 'exists:tipo_vehiculo,id'],
            'fotografia'          => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nro_placa'           => 'número de placa',
            'anio'                => 'año',
            'marca'               => 'marca',
            'estado_vehiculo'     => 'estado',
            'id_tipo_combustible' => 'tipo de combustible',
            'id_tipo_vehiculo'    => 'tipo de vehículo',
            'fotografia'          => 'fotografía',
        ];
    }
}
