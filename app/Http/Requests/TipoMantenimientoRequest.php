<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TipoMantenimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('tipo_mantenimiento')?->id;

        return [
            'tipo_mantenimiento'        => ['required', 'string', 'max:150', Rule::unique('tipo_mantenimiento', 'tipo_mantenimiento')->ignore($id)],
            'estado_tipo_mantenimiento' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'tipo_mantenimiento'        => 'tipo de mantenimiento',
            'estado_tipo_mantenimiento' => 'estado',
        ];
    }
}
