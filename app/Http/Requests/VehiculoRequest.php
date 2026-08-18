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
            'nro_placa' => ['required', 'string', 'max:20', Rule::unique('vehiculo', 'nro_placa')->ignore($vehiculo?->id)->whereNull('deleted_at')],
            'codigo' => ['nullable', 'string', 'max:50'],
            'anio' => ['nullable', 'string', 'max:4', 'regex:/^\d{4}$/'],
            'marca' => ['nullable', 'string', 'max:50'],
            'modelo' => ['nullable', 'string', 'max:50'],
            'estado_vehiculo' => ['required', Rule::in(['ACTIVO', 'RETIRADO', 'VENDIDO'])],
            'tipo_medicion' => ['required', Rule::in(['kilometraje', 'horometro'])],
            'id_tipo_combustible' => ['required', 'integer', 'exists:tipo_combustible,id'],
            'id_tipo_vehiculo' => ['required', 'integer', 'exists:tipo_vehiculo,id'],
            'fotografia' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:2048'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nro_placa' => 'número de placa',
            'codigo' => 'código contable',
            'anio' => 'año',
            'marca' => 'marca',
            'modelo' => 'modelo',
            'estado_vehiculo' => 'estado',
            'tipo_medicion' => 'tipo de medición',
            'id_tipo_combustible' => 'tipo de combustible',
            'id_tipo_vehiculo' => 'tipo de vehículo',
            'fotografia' => 'fotografía',
        ];
    }
}
