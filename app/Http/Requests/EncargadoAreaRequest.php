<?php

namespace App\Http\Requests;

use App\Models\Persona;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EncargadoAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAnyRole(['super-admin', 'administrador']);
    }

    protected function failedAuthorization(): never
    {
        throw new AuthorizationException('Sólo un administrador puede asignar encargados de área.');
    }

    public function rules(): array
    {
        return [
            'id_persona' => ['required', 'integer', 'exists:persona,id'],
            'tipo_encargo' => ['required', Rule::in(['TITULAR', 'SUPLENTE'])],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:today'],
            'motivo' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
        ];
    }

    /**
     * Reglas que dependen de datos relacionados (persona activa, si ya
     * tiene cuenta de usuario) y no se expresan como reglas declarativas.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $persona = Persona::find($this->input('id_persona'));

            if (! $persona) {
                return;
            }

            if ($persona->estado_persona !== 'ACTIVO') {
                $validator->errors()->add('id_persona', 'La persona seleccionada no está activa.');
            }

            if (! $persona->user && ! $this->filled('email')) {
                $validator->errors()->add('email', 'La persona no tiene una cuenta de usuario; ingrese un correo para crearla.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'id_persona' => 'persona',
            'tipo_encargo' => 'tipo de encargo',
            'fecha_fin' => 'fecha de finalización',
            'motivo' => 'motivo',
            'email' => 'correo electrónico',
        ];
    }
}
