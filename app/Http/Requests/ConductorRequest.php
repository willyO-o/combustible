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
        // verificar conductores eliminados ya que se esta usando softDeletes, para que no se pueda crear un conductor con el mismo carnet de identidad que uno eliminado
        $conductor = $this->route('conductor');

        return [
            'ci'               => ['sometimes', 'required', 'string', 'max:20', Rule::unique('conductor', 'ci')->ignore($conductor?->id)],
            'nombres'          => ['sometimes', 'required', 'string', 'max:150'],
            'paterno'          => ['sometimes', 'nullable', 'string', 'max:150','required_without:materno'],
            'materno'          => ['sometimes', 'nullable', 'string', 'max:150','required_without:paterno'],
            'foto'             => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:2048'],
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
