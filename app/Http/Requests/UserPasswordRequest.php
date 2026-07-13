<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (!Hash::check($value, $this->route('usuario')->password)) {
                        $fail('La contraseña actual no es correcta.');
                    }
                },
            ],
            'new_password' => ['required', 'string', Password::min(8)->letters()->numbers(), 'confirmed'],
            'new_password_confirmation' => ['required'],
        ];
    }

    public function attributes(): array
    {
        return [
            'current_password'          => 'contraseña actual',
            'new_password'              => 'nueva contraseña',
            'new_password_confirmation' => 'confirmación de contraseña',
        ];
    }
}
