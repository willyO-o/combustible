<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $usuario = $this->route('usuario');
        $isEdit  = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario?->id)],
            'password' => $isEdit
                ? ['nullable', 'string', Password::min(8)->letters()->numbers()]
                : ['required', 'string', Password::min(8)->letters()->numbers(), 'confirmed'],
            'password_confirmation' => $isEdit ? ['nullable'] : ['required'],
            'roles'    => ['nullable', 'array'],
            'roles.*'  => ['integer', 'exists:rol,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name'     => 'nombre',
            'email'    => 'correo electrónico',
            'password' => 'contraseña',
            'roles'    => 'roles',
        ];
    }
}
