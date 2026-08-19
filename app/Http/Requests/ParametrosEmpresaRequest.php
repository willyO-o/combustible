<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ParametrosEmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_empresa' => ['required', 'string', 'max:255'],
            'direccion_empresa' => ['required', 'string', 'max:255'],
            'telefono_empresa' => ['required', 'string', 'max:20'],
            'correo_empresa' => ['required', 'email', 'max:255'],
            'nit_empresa' => ['required', 'string', 'max:30'],
            'logo_empresa' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'parametros_vale' => ['required', 'array'],
            'parametros_vale.tiempo_expiracion' => ['required', 'integer', 'min:1'],
            'estado' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre_empresa' => 'nombre de la empresa',
            'direccion_empresa' => 'dirección',
            'telefono_empresa' => 'teléfono',
            'correo_empresa' => 'correo electrónico',
            'nit_empresa' => 'NIT',
            'logo_empresa' => 'logo',
            'parametros_vale.tiempo_expiracion' => 'tiempo de expiración del vale',
            'estado' => 'estado',
        ];
    }
}
