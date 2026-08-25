<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'es_al_exterior' => ['sometimes', 'boolean'],
            // Obligatorio únicamente cuando la carga se marca como "al exterior".
            'pais' => [Rule::requiredIf(fn () => $this->boolean('es_al_exterior')), 'nullable', 'string', 'max:50'],
            // Detalle lo puede registrar el propio conductor, sin restricción
            // de rol (a diferencia de observaciones, ver más abajo).
            'detalle' => ['nullable', 'string', 'max:50'],
            // Sólo un jefe de área (o roles superiores) puede definir
            // observaciones: CargaMaterialController filtra este campo según
            // el rol antes de persistir, tanto al abrir como al editar la
            // carga, en vez de rechazar la petición aquí.
            'observaciones' => ['nullable', 'string'],
        ];

        // Sólo la API permite abrir la carga (y, opcionalmente, su primer
        // viaje) como un registro offline sincronizado después (ver
        // CargaMaterialController::store() en Api/V1): is_offline exige
        // entonces fecha_apertura y, si se envía el bloque "viaje", también
        // viaje.fecha_hora_carga — ambos comparten el mismo momento offline.
        if (! $editando && $this->is('api/*')) {
            $reglas['is_offline'] = ['sometimes', 'boolean'];
            $reglas['fecha_apertura'] = [$this->boolean('is_offline') ? 'required' : 'nullable', 'date'];

            if ($this->has('viaje')) {
                $reglas = array_merge($reglas, [
                    'viaje.id_material' => ['required', 'integer', 'exists:material,id'],
                    'viaje.foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:8192'],
                    'viaje.origen' => ['required', 'string', 'max:255'],
                    'viaje.destino' => ['required', 'string', 'max:255'],
                    'viaje.detalle' => ['nullable', 'string', 'max:255'],
                    'viaje.fecha_hora_carga' => [$this->boolean('is_offline') ? 'required' : 'nullable', 'date'],
                ]);
            }
        }

        return $reglas;
    }

    public function attributes(): array
    {
        return [
            'id_vehiculo_externo' => 'vehículo',
            'nombre_conductor' => 'nombre del conductor',
            'telefono' => 'teléfono',
            'es_al_exterior' => 'al exterior',
            'pais' => 'país',
            'detalle' => 'detalle',
            'observaciones' => 'observaciones',
            'fecha_apertura' => 'fecha de apertura',
            'is_offline' => 'registro sin conexión',
            'viaje.id_material' => 'material del viaje',
            'viaje.foto' => 'foto de evidencia del viaje',
            'viaje.origen' => 'origen del viaje',
            'viaje.destino' => 'destino del viaje',
            'viaje.detalle' => 'detalle del viaje',
            'viaje.fecha_hora_carga' => 'fecha y hora de carga del viaje',
        ];
    }

    public function messages(): array
    {
        return [
            'id_vehiculo_externo.integer' => 'Seleccione un vehículo válido.',
            'id_vehiculo_externo.exists' => 'Seleccione un vehículo válido.',
            'pais.required' => 'El país es obligatorio cuando el flete es al exterior.',
        ];
    }
}
