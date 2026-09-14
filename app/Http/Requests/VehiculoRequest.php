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
            'nro_placa' => ['nullable', 'string', 'max:20', Rule::unique('vehiculo', 'nro_placa')->ignore($vehiculo?->id)->whereNull('deleted_at')],
            'codigo' => ['nullable', 'string', 'max:50'],
            'anio' => ['nullable', 'string', 'max:4', 'regex:/^\d{4}$/'],
            'marca' => ['nullable', 'string', 'max:50'],
            'modelo' => ['nullable', 'string', 'max:50'],
            'estado_vehiculo' => ['required', Rule::in(['ACTIVO', 'RETIRADO', 'VENDIDO'])],
            'tipo_medicion' => ['required', Rule::in(['kilometraje', 'horometro'])],
            'id_tipo_combustible' => ['required', 'integer', 'exists:tipo_combustible,id'],
            'id_tipo_vehiculo' => ['required', 'integer', 'exists:tipo_vehiculo,id'],
            // Obligatoria al registrar (POST); al editar (PUT/_method spoofed)
            // se deja "sometimes" para no forzar resubir la foto si no cambia
            // (VehiculoController::update() conserva la actual cuando no
            // llega un archivo nuevo).
            'fotografia' => [$this->method() === 'POST' ? 'required' : 'sometimes', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:2048'],
            'capacidad' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'capacidad_unidad' => ['nullable', 'string', 'max:20', 'required_with:capacidad'],
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
            'capacidad' => 'capacidad',
            'capacidad_unidad' => 'unidad de capacidad',
        ];
    }
}
