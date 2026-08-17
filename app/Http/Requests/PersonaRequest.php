<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PersonaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $persona = $this->route('persona');

        return [
            'ci' => ['required', 'string', 'max:20', Rule::unique('persona', 'ci')->ignore($persona?->id)],
            'nombres' => ['required', 'string', 'max:150'],
            'paterno' => ['nullable', 'string', 'max:150', 'required_without:materno'],
            'materno' => ['nullable', 'string', 'max:150', 'required_without:paterno'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:2048'],
            'celular' => ['nullable', 'string', 'max:20'],
            'direccion' => ['nullable', 'string', 'max:250'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'estado_persona' => ['required', Rule::in(['ACTIVO', 'INACTIVO', 'RETIRADO'])],

            'tipo' => ['required', Rule::in(['conductor', 'jefe-area', 'personal'])],

            'crear_usuario' => ['sometimes', 'boolean'],
            'email' => [
                'required_if:crear_usuario,true',
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($persona?->user?->id),
            ],
            'estado_usuario' => ['required_if:crear_usuario,true', Rule::in(['ACTIVO', 'INACTIVO'])],

            'estado_conductor' => ['required_if:tipo,conductor', Rule::in(['ACTIVO', 'INACTIVO', 'RETIRADO'])],
            'id_vehiculo' => ['nullable', 'exists:vehiculo,id'],
            'fecha_asignacion' => ['nullable', 'date'],

            'id_area' => ['required_if:tipo,jefe-area', 'exists:area,id'],
            'tipo_encargo' => ['required_if:tipo,jefe-area', Rule::in(['TITULAR', 'SUPLENTE'])],
            'fecha_inicio_encargo' => ['nullable', 'date'],
            'motivo_encargo' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'ci' => 'carnet de identidad',
            'nombres' => 'nombres',
            'paterno' => 'apellido paterno',
            'materno' => 'apellido materno',
            'foto' => 'foto',
            'celular' => 'celular',
            'direccion' => 'dirección',
            'fecha_nacimiento' => 'fecha de nacimiento',
            'estado_persona' => 'estado',
            'tipo' => 'tipo de registro',
            'email' => 'correo electrónico',
            'estado_usuario' => 'estado del usuario',
            'estado_conductor' => 'estado del conductor',
            'id_vehiculo' => 'vehículo',
            'fecha_asignacion' => 'fecha de asignación',
            'id_area' => 'área',
            'tipo_encargo' => 'tipo de encargo',
            'fecha_inicio_encargo' => 'fecha de inicio del encargo',
            'motivo_encargo' => 'motivo',
        ];
    }
}
