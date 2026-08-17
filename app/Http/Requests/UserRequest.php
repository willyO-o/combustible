<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $usuario = $this->route('usuario');
        $isEdit = $this->isMethod('PUT') || $this->isMethod('PATCH');
        // En edición, una cuenta de sistema sin persona vinculada (ej. administradores
        // sembrados directamente) no tiene tipo/rol operativo que gestionar aquí.
        $requiereTipo = ! $isEdit || $usuario?->persona !== null;

        return [
            'id_persona' => [
                Rule::requiredIf(! $isEdit),
                'exists:persona,id',
                Rule::unique('users', 'id_persona'),
            ],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario?->id)],
            'estado_usuario' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],

            'tipo' => ['nullable', Rule::requiredIf($requiereTipo), Rule::in(['conductor', 'jefe-area', 'personal'])],

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
            'id_persona' => 'persona',
            'email' => 'correo electrónico',
            'estado_usuario' => 'estado del usuario',
            'tipo' => 'tipo de registro',
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
