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
        $id = $this->route('tipo_vehiculo')?->id;

        return [
            'tipo_vehiculo'        => ['required', 'string', 'max:150', Rule::unique('tipo_vehiculo', 'tipo_vehiculo')->ignore($id)],
            'estado_tipo_vehiculo' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'tipo_vehiculo'        => 'tipo de vehículo',
            'estado_tipo_vehiculo' => 'estado',
        ];
    }
}
