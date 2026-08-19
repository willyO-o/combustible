<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RolRequest extends FormRequest
{
    /**
     * Sólo administrador/super-admin gestionan roles y permisos, y ningún
     * rol oculto (ver config/acl.php) puede editarse desde este formulario.
     */
    public function authorize(): bool
    {
        if (! $this->user()?->hasAnyRole(['super-admin', 'administrador'])) {
            return false;
        }

        $role = $this->route('role');

        return ! $role || ! in_array($role->name, config('acl.roles_ocultos'), true);
    }

    protected function failedAuthorization(): never
    {
        throw new AuthorizationException('Sólo un administrador puede gestionar roles y permisos.');
    }

    public function rules(): array
    {
        $rules = [
            'permisos' => ['array'],
            'permisos.*' => ['string', 'exists:permissions,name'],
        ];

        // El nombre sólo se pide al crear: los roles existentes no se pueden
        // renombrar, porque el código autoriza con hasRole('nombre-literal')
        // en varios controladores y un cambio de nombre rompería esos checks.
        if ($this->isMethod('post')) {
            $rules['name'] = [
                'required',
                'string',
                'max:125',
                Rule::unique('roles', 'name'),
                Rule::notIn(config('acl.roles_ocultos')),
            ];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre del rol',
            'permisos' => 'permisos',
        ];
    }
}
