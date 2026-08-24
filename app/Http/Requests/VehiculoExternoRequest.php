<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehiculoExternoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('vehiculoExterno')?->id;

        return [
            'nro_placa' => ['required', 'string', 'max:20', Rule::unique('vehiculo_externo', 'nro_placa')->ignore($id)],
            'propietario' => ['nullable', 'string', 'max:250'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nro_placa' => 'placa',
            'propietario' => 'propietario',
        ];
    }
}
