<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ValeRequest extends FormRequest
{
    /**
     * En el módulo web el gating de "quién puede emitir un vale" es sólo de
     * frontend (`v-can="'vales.crear'"`). La API no tiene esa capa, así que al
     * emitir un vale por la API se exige aquí el permiso `vales.crear`
     * (jefe-area y administrador lo tienen; super-admin por el bypass global).
     * Tras `auth:api` el guard por defecto es `api` y los permisos viven en
     * `web`, por eso se consulta el guard explícitamente.
     */
    public function authorize(): bool
    {
        if ($this->isMethod('POST') && $this->is('api/*')) {
            $user = $this->user();

            return (bool) $user && ($user->hasRole('super-admin') || $user->hasPermissionTo('vales.crear', 'web'));
        }

        return true;
    }

    protected function failedAuthorization(): never
    {
        throw new AuthorizationException('No tiene permiso para emitir vales.');
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

    /**
     * La fecha de vencimiento se recibe sólo como día (Y-m-d), tanto en el
     * formulario web como en la API: el vale queda vigente durante todo ese
     * día, hasta las 23:59:59.
     */
    protected function prepareForValidation(): void
    {
        $fechaVencimiento = $this->input('fecha_vencimiento');

        if (is_string($fechaVencimiento) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaVencimiento)) {
            $this->merge(['fecha_vencimiento' => "{$fechaVencimiento} 23:59:59"]);
        }
    }

    public function rules(): array
    {
        return [
            'litros' => ['required', 'numeric', 'min:0.01', 'max:9999.99'],
            'precio' => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            // Cualquier vehículo (también para un jefe de área, aunque no sea de
            // sus áreas: a pedido del usuario, la asignación a áreas todavía no
            // es confiable).
            'id_vehiculo' => ['required', 'integer', 'exists:vehiculo,id'],
            // Al emitir, cualquier conductor ACTIVO (esté o no asignado al
            // vehículo: la asignación todavía no es confiable). Al editar el
            // conductor ya está fijo, así que basta con que exista aunque
            // después haya quedado inactivo.
            'id_conductor' => $this->isMethod('POST')
                ? ['required', 'integer', Rule::exists('conductor', 'id')->where('estado_conductor', 'ACTIVO')->whereNull('deleted_at')]
                : ['required', 'integer', 'exists:conductor,id'],
            'id_grifo' => ['required', 'integer', 'exists:grifo,id'],
            // En la API es opcional: si no se envía, Vale::boot() la calcula
            // como fecha de emisión + tiempo_expiracion (ver openapi.yaml).
            'fecha_vencimiento' => [$this->is('api/*') ? 'nullable' : 'required', 'date', 'after_or_equal:tomorrow'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_vencimiento.after_or_equal' => 'La fecha de vencimiento debe ser posterior a hoy.',
            'id_conductor.exists' => 'El conductor seleccionado no existe o no está activo.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nro_vale' => 'número de vale',
            'fecha_emision' => 'fecha de emisión',
            'fecha_vencimiento' => 'fecha de vencimiento',
            'litros' => 'litros',
            'precio' => 'precio',
            'id_vehiculo' => 'vehículo',
            'id_conductor' => 'conductor',
            'id_grifo' => 'estación de servicio',
            'estado_vale' => 'estado',
            'id_tipo_combustible' => 'tipo de combustible',
        ];
    }
}
