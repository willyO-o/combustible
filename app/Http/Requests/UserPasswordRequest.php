<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Cambio de contraseña de OTRO usuario desde el módulo Usuarios
 * (permiso usuarios.contrasena.cambiar). No pide la contraseña actual: quien
 * administra la cuenta no la conoce. El propio usuario cambia la suya desde
 * Mi Perfil (Auth\PasswordController), que sí exige la contraseña actual.
 */
class UserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'new_password' => ['required', 'string', Password::min(8)->letters()->numbers(), 'confirmed'],
            'new_password_confirmation' => ['required'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'new_password' => 'nueva contraseña',
            'new_password_confirmation' => 'confirmación de contraseña',
        ];
    }
}
