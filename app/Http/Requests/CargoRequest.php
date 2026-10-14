<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CargoRequest extends FormRequest
{
    use RespondeErroresEnJson;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza espacios para que " Técnico " y "Técnico" no se registren
     * como dos cargos distintos (el unique de la BD compara el texto tal cual).
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->cargo)) {
            $this->merge(['cargo' => preg_replace('/\s+/u', ' ', trim($this->cargo))]);
        }
    }

    public function rules(): array
    {
        $id = $this->route('cargo')?->id;

        return [
            'cargo' => ['required', 'string', 'max:150', Rule::unique('cargo', 'cargo')->ignore($id)],
            'estado_cargo' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'cargo' => 'cargo',
            'estado_cargo' => 'estado',
        ];
    }

    public function messages(): array
    {
        return [
            'cargo.unique' => 'Ya existe un cargo con ese nombre.',
        ];
    }
}
