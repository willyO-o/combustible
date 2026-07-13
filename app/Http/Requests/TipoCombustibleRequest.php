<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TipoCombustibleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('tipoCombustible');

        return [
            'tipo_combustible'        => ['required', 'string', 'max:100', Rule::unique('tipo_combustible', 'tipo_combustible')->ignore($id)],
            'estado_tipo_combustible' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'tipo_combustible'        => 'tipo de combustible',
            'estado_tipo_combustible' => 'estado',
        ];
    }
}
