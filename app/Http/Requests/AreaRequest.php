<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('area')?->id;

        return [
            'nombre_area' => ['required', 'string', 'max:200', Rule::unique('area', 'nombre_area')->ignore($id)],
            'descripcion_area' => ['nullable', 'string'],
            'estado_area' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre_area' => 'nombre del área',
            'descripcion_area' => 'descripción',
            'estado_area' => 'estado',
        ];
    }
}
