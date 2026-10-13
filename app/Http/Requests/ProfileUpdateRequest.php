<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * El correo llega sin espacios alrededor, igual que se compara al iniciar
     * sesión.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => trim($this->input('email'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * El correo es editable por el propio usuario, pero como es su
     * credencial de acceso (web y app), cambiarlo exige confirmar la
     * contraseña actual: así una sesión olvidada abierta no basta para
     * apropiarse de la cuenta. Omitir `email` deja el correo como está.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'current_password' => [Rule::requiredIf(fn () => $this->cambiaElCorreo()), 'nullable', 'current_password:web'],
            // Se persisten en persona.celular/persona.direccion (ver
            // ProfileController::update()), no en users: solo aplican si el
            // usuario tiene una persona vinculada.
            'celular' => ['nullable', 'string', 'max:20'],
            'direccion' => ['nullable', 'string', 'max:250'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:2048'],
        ];
    }

    /**
     * El correo cambia si llega uno distinto al actual (ver User::cambiaraElCorreoA()).
     */
    public function cambiaElCorreo(): bool
    {
        return $this->user()->cambiaraElCorreoA($this->input('email'));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Este correo electrónico ya está registrado por otro usuario.',
            'current_password.required' => 'Ingresa tu contraseña actual para cambiar el correo electrónico.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'correo electrónico',
            'current_password' => 'contraseña actual',
            'celular' => 'celular',
            'direccion' => 'dirección',
            'foto' => 'foto',
        ];
    }
}
