<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ViajeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['super-admin', 'administrador', 'conductor', 'jefe-area']) ?? false;
    }

    public function rules(): array
    {
        // La app Flutter permite registrar viajes sin conexión y los sincroniza
        // después: para ese momento ya no corresponde la fecha/hora actual del
        // servidor, sino la real en que se generó el viaje offline. is_offline
        // (sólo se respeta viniendo de la API) exige entonces fecha_hora_carga.
        $esRegistroOffline = $this->is('api/*') && $this->boolean('is_offline');

        return [
            'id_material' => ['required', 'integer', 'exists:material,id'],
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:8192'],
            'origen' => ['required', 'string', 'max:255'],
            'destino' => ['required', 'string', 'max:255'],
            'detalle' => ['nullable', 'string', 'max:255'],
            'fecha_hora_carga' => [$esRegistroOffline ? 'required' : 'nullable', 'date'],
            // Sólo tiene efecto en la API (ver $esRegistroOffline arriba): marca
            // que el viaje se capturó sin conexión en la app y se sincroniza después.
            'is_offline' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'id_material' => 'material',
            'foto' => 'foto de evidencia',
            'origen' => 'origen',
            'destino' => 'destino',
            'detalle' => 'detalle',
            'fecha_hora_carga' => 'fecha y hora de carga',
            'is_offline' => 'registro sin conexión',
        ];
    }
}
