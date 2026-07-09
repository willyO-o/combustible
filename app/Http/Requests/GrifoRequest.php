<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GrifoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $grifo = $this->route('grifo');

        return [
            'razon_social'  => ['required', 'string', 'max:255'],
            'nit'           => ['required', 'string', 'max:30', Rule::unique('grifo', 'nit')->ignore($grifo?->id)->whereNull('deleted_at')],
            'direccion'     => ['nullable', 'string', 'max:250'],
            'ciudad'        => ['nullable', 'string', 'max:100'],
            'telefono'      => ['nullable', 'string', 'max:20'],
            'estado_grifo'  => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'razon_social' => 'razón social',
            'nit'          => 'NIT',
            'direccion'    => 'dirección',
            'ciudad'       => 'ciudad',
            'telefono'     => 'teléfono',
            'estado_grifo' => 'estado',
        ];
    }
}
