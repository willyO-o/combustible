<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Emisión simplificada de una orden de trabajo a partir de una solicitud
 * PENDIENTE (POST /solicitudes-mantenimiento/{solicitud}/orden-trabajo): sólo
 * se reciben los datos editables; vehículo, conductor, categoría y lecturas se
 * heredan de la solicitud (mismo criterio que OrdenTrabajo/Create.vue).
 *
 * También autoriza el GET del formulario (.../orden-trabajo/formulario), que
 * no recibe cuerpo: por eso rules() sólo valida en POST.
 */
class EmitirOrdenTrabajoRequest extends FormRequest
{
    /**
     * Exige el permiso `mantenimiento.ordenes.crear` (el mismo que muestra el
     * botón "Generar Orden de Trabajo" en el sistema web; lo tienen jefe-area
     * y administrador, y super-admin por el bypass global). Tras `auth:api` el
     * guard por defecto es `api` y los permisos viven en `web`, por eso se
     * consulta con guard explícito (ver .ai/rules/v1.md). checkPermissionTo()
     * devuelve false, en vez de lanzar excepción, si el permiso no existe.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) $user
            && ($user->hasRole('super-admin') || $user->checkPermissionTo('mantenimiento.ordenes.crear', 'web'));
    }

    protected function failedAuthorization(): never
    {
        throw new AuthorizationException('No tiene permiso para generar órdenes de trabajo.');
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->expectsJson()) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Los datos enviados no son válidos.',
                'errors' => $validator->errors(),
            ], 422));
        }

        parent::failedValidation($validator);
    }

    public function rules(): array
    {
        if (! $this->isMethod('POST')) {
            return [];
        }

        return [
            'id_usuario_ejecuta' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) {
                    // Técnico de mantenimiento ACTIVO (los mismos que ofrece el
                    // combo del formulario web), en una sola consulta. Se filtra
                    // por la relación `roles` y no con User::role() porque bajo
                    // auth:api el guard por defecto es 'api' (ver .ai/rules/v1.md).
                    $esTecnicoActivo = User::whereKey($value)
                        ->where('estado_usuario', 'ACTIVO')
                        ->whereHas('roles', fn ($query) => $query->where('name', 'tecnico-mantenimiento'))
                        ->exists();

                    if (! $esTecnicoActivo) {
                        $fail('El responsable de ejecución debe ser un técnico de mantenimiento activo.');
                    }
                },
            ],
            'nota_emisor' => ['required', 'string', 'max:1000'],
            'id_taller' => ['nullable', 'integer', Rule::exists('taller', 'id')->where('estado_taller', 'ACTIVO')],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_usuario_ejecuta.required' => 'Debe asignar un responsable de ejecución.',
            'nota_emisor.required' => 'Debe indicar el trabajo a realizar (nota del emisor).',
            'id_taller.exists' => 'El taller seleccionado no existe o no está activo.',
        ];
    }
}
