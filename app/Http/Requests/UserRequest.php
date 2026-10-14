<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

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

        $rolesAsignables = Role::whereNotIn('name', config('acl.roles_ocultos'))->pluck('name')->all();
        $tieneRol = fn (string $rol) => in_array($rol, $this->input('roles', []), true);

        return [
            'id_persona' => [
                Rule::requiredIf(! $isEdit),
                'exists:persona,id',
                Rule::unique('users', 'id_persona'),
            ],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario?->id)],
            'estado_usuario' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
            // Contraseña inicial: sólo al crear la cuenta (al editar se descarta;
            // el cambio de contraseña tiene su propia pantalla). Vacía = formato
            // por defecto CI#Plusmetals (User::contrasenaInicial).
            'password' => [$isEdit ? 'exclude' : 'nullable', 'string', 'max:255', Password::min(8)->letters()->numbers()],

            // Un usuario puede tener varios roles a la vez. Sin roles seleccionados,
            // queda como "personal" (cuenta con acceso, sin rol operativo).
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'distinct', Rule::in($rolesAsignables)],

            'estado_conductor' => [Rule::requiredIf(fn () => $tieneRol('conductor')), Rule::in(['ACTIVO', 'INACTIVO', 'RETIRADO'])],
            'id_vehiculo' => ['nullable', 'exists:vehiculo,id'],
            'fecha_asignacion' => ['nullable', 'date'],

            'id_area' => [Rule::requiredIf(fn () => $tieneRol('jefe-area')), 'exists:area,id'],
            'tipo_encargo' => [Rule::requiredIf(fn () => $tieneRol('jefe-area')), Rule::in(['TITULAR', 'SUPLENTE'])],
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
            'password' => 'contraseña inicial',
            'roles' => 'roles',
            'roles.*' => 'rol',
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
