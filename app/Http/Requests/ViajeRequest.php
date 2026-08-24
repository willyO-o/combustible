<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ViajeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['super-admin', 'administrador', 'conductor', 'jefe-area']) ?? false;
    }

    public function rules(): array
    {
        return [
            'id_material' => ['required', 'integer', 'exists:material,id'],
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:8192'],
            'origen' => ['required', 'string', 'max:255'],
            'destino' => ['required', 'string', 'max:255'],
            'detalle' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'id_material' => 'material',
            'foto' => 'foto de evidencia',
            'origen' => 'origen',
            'destino' => 'destino',
            'detalle' => 'detalle',
        ];
    }
}
