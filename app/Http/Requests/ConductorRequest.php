<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConductorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $conductor = $this->route('conductor');

        return [
            'ci'               => ['required', 'string', 'max:20', Rule::unique('conductor', 'ci')->ignore($conductor?->id)->whereNull('deleted_at')],
            'nombres'          => ['required', 'string', 'max:150'],
            'paterno'          => ['nullable', 'string', 'max:150'],
            'materno'          => ['nullable', 'string', 'max:150'],
            'foto'             => [$conductor ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'celular'          => ['nullable', 'string', 'max:20'],
            'direccion'        => ['nullable', 'string', 'max:250'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'estado_conductor' => ['required', Rule::in(['ACTIVO', 'INACTIVO', 'RETIRADO'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'ci'               => 'carnet de identidad',
            'nombres'          => 'nombres',
            'paterno'          => 'apellido paterno',
            'materno'          => 'apellido materno',
            'foto'             => 'foto',
            'celular'          => 'celular',
            'direccion'        => 'dirección',
            'fecha_nacimiento' => 'fecha de nacimiento',
            'estado_conductor' => 'estado',
        ];
    }
}
