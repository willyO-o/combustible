<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * El correo NO se valida aquí a propósito: no es editable desde el
     * perfil (solo un administrador puede cambiarlo, desde el módulo de
     * Usuarios). Al no tener regla, nunca llega a `validated()`, y
     * ProfileController::update() además lo omite explícitamente por
     * seguridad si de todas formas se envía en el cuerpo de la petición.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Se persisten en persona.celular/persona.direccion (ver
            // ProfileController::update()), no en users: solo aplican si el
            // usuario tiene una persona vinculada.
            'celular' => ['nullable', 'string', 'max:20'],
            'direccion' => ['nullable', 'string', 'max:250'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:2048'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'celular' => 'celular',
            'direccion' => 'dirección',
            'foto' => 'foto',
        ];
    }
}
