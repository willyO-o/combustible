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

        $reglas = [
            // Al editar, el formulario no muestra ni permite cambiar el
            // vehículo externo, pero Inertia igual reenvía el campo (vacío)
            // en el payload: "nullable" evita que ese valor vacío dispare un
            // error de validación sobre un campo que ni siquiera se edita.
            'id_vehiculo_externo' => $editando
                ? ['sometimes', 'nullable', 'integer', 'exists:vehiculo_externo,id']
                : ['required', 'integer', 'exists:vehiculo_externo,id'],
            'nombre_conductor' => ['nullable', 'string', 'max:250'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'observaciones' => ['nullable', 'string'],
        ];

        // Sólo la API permite registrar el primer viaje junto con la apertura
        // de la carga (ver CargaMaterialController::store() en Api/V1): si el
        // cliente envía el bloque "viaje", sus campos pasan a ser obligatorios,
        // igual que en ViajeRequest.
        if (! $editando && $this->is('api/*') && $this->has('viaje')) {
            $reglas = array_merge($reglas, [
                'viaje.id_material' => ['required', 'integer', 'exists:material,id'],
                'viaje.foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:8192'],
                'viaje.origen' => ['required', 'string', 'max:255'],
                'viaje.destino' => ['required', 'string', 'max:255'],
                'viaje.detalle' => ['nullable', 'string', 'max:255'],
            ]);
        }

        return $reglas;
    }

    public function attributes(): array
    {
        return [
            'id_vehiculo_externo' => 'vehículo',
            'nombre_conductor' => 'nombre del conductor',
            'telefono' => 'teléfono',
            'observaciones' => 'observaciones',
            'viaje.id_material' => 'material del viaje',
            'viaje.foto' => 'foto de evidencia del viaje',
            'viaje.origen' => 'origen del viaje',
            'viaje.destino' => 'destino del viaje',
            'viaje.detalle' => 'detalle del viaje',
        ];
    }

    public function messages(): array
    {
        return [
            'id_vehiculo_externo.integer' => 'Seleccione un vehículo válido.',
            'id_vehiculo_externo.exists' => 'Seleccione un vehículo válido.',
        ];
    }
}
