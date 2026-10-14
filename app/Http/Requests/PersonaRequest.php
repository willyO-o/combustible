<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class PersonaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Roles que sólo tienen sentido con una cuenta de usuario: los de
     * conductor y jefe de área además crean sus propios registros de
     * dominio (conductor, encargo de área) aunque no se cree el usuario.
     *
     * @var array<int, string>
     */
    private const ROLES_CON_REGISTRO_PROPIO = ['conductor', 'jefe-area'];

    public function rules(): array
    {
        $persona = $this->route('persona');

        $rolesAsignables = Role::whereNotIn('name', config('acl.roles_ocultos'))->pluck('name')->all();
        $tieneRol = fn (string $rol) => in_array($rol, (array) $this->input('roles', []), true);
        $tendraUsuario = $this->boolean('crear_usuario') || $persona?->user !== null;

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
            // Cargo del personal (opcional). Sólo cargos ACTIVOS; al editar
            // también se acepta el que ya tiene aunque luego se haya
            // desactivado, para no bloquear la edición de otros datos.
            'id_cargo' => [
                'nullable',
                'integer',
                Rule::exists('cargo', 'id')->where(fn ($query) => $query->where(
                    fn ($query) => $query->where('estado_cargo', 'ACTIVO')->orWhere('id', $persona?->id_cargo)
                )),
            ],

            // Una persona puede tener varios roles a la vez (mismo catálogo
            // que el módulo de Usuarios: todos salvo los ocultos). Sin roles,
            // queda como "personal".
            'roles' => ['nullable', 'array'],
            'roles.*' => [
                'string',
                'distinct',
                Rule::in($rolesAsignables),
                function (string $attribute, mixed $rol, \Closure $fail) use ($tendraUsuario) {
                    if (! $tendraUsuario && ! in_array($rol, self::ROLES_CON_REGISTRO_PROPIO, true)) {
                        $fail('Para asignar el rol '.self::etiquetaRol($rol).' debe crear el usuario de acceso.');
                    }
                },
            ],

            'crear_usuario' => ['sometimes', 'boolean'],
            'email' => [
                'required_if:crear_usuario,true',
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($persona?->user?->id),
            ],
            'estado_usuario' => ['required_if:crear_usuario,true', Rule::in(['ACTIVO', 'INACTIVO'])],
            // Contraseña inicial de la cuenta: sólo cuenta al CREARLA (sin
            // usuario previo). Vacía = formato por defecto CI#Plusmetals
            // (User::contrasenaInicial). Mismo mínimo que el cambio de contraseña.
            'password' => [
                Rule::excludeIf(fn () => ! $this->boolean('crear_usuario') || $persona?->user !== null),
                'nullable', 'string', 'max:255', Password::min(8)->letters()->numbers(),
            ],

            'estado_conductor' => [Rule::requiredIf(fn () => $tieneRol('conductor')), Rule::in(['ACTIVO', 'INACTIVO', 'RETIRADO'])],
            'id_vehiculo' => ['nullable', 'exists:vehiculo,id'],
            'fecha_asignacion' => ['nullable', 'date'],

            'id_area' => [Rule::requiredIf(fn () => $tieneRol('jefe-area')), 'nullable', 'exists:area,id'],
            'tipo_encargo' => [Rule::requiredIf(fn () => $tieneRol('jefe-area')), Rule::in(['TITULAR', 'SUPLENTE'])],
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
            'id_cargo' => 'cargo',
            'roles' => 'roles',
            'roles.*' => 'rol',
            'email' => 'correo electrónico',
            'estado_usuario' => 'estado del usuario',
            'password' => 'contraseña inicial',
            'estado_conductor' => 'estado del conductor',
            'id_vehiculo' => 'vehículo',
            'fecha_asignacion' => 'fecha de asignación',
            'id_area' => 'área',
            'tipo_encargo' => 'tipo de encargo',
            'fecha_inicio_encargo' => 'fecha de inicio del encargo',
            'motivo_encargo' => 'motivo',
        ];
    }

    public function messages(): array
    {
        return [
            'id_cargo.exists' => 'El cargo seleccionado no existe o está inactivo.',
        ];
    }

    private static function etiquetaRol(string $rol): string
    {
        return match ($rol) {
            'administrador' => 'Administrador',
            'tecnico-mantenimiento' => 'Técnico de Mantenimiento',
            default => $rol,
        };
    }
}
