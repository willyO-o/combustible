<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CargaMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La verificación fina de "puede editar ESTA carga" (dueño + que
        // siga ABIERTA) la hace CargaMaterialController::assertPuedeGestionar(),
        // llamada explícitamente al inicio de update(); aquí solo se filtra
        // por rol.
        return $this->user()?->hasAnyRole(['super-admin', 'administrador', 'conductor', 'jefe-area']) ?? false;
    }

    public function rules(): array
    {
        // Al editar no se permite cambiar el vehículo externo (ya puede
        // tener viajes registrados en base a él): solo es obligatorio al
        // abrir la carga. El material ya no se define a nivel de carga,
        // sino en cada viaje (ver ViajeRequest).
        $editando = (bool) $this->route('cargaMaterial');

        return [
            'id_vehiculo_externo' => [$editando ? 'sometimes' : 'required', 'integer', 'exists:vehiculo_externo,id'],
            'nombre_conductor' => ['nullable', 'string', 'max:250'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'observaciones' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'id_vehiculo_externo' => 'vehículo',
            'nombre_conductor' => 'nombre del conductor',
            'telefono' => 'teléfono',
            'observaciones' => 'observaciones',
        ];
    }
}
