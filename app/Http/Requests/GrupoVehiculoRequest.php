<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GrupoVehiculoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('grupoVehiculo')?->id;

        return [
            'grupo_vehiculo' => ['required', 'string', 'max:150', Rule::unique('grupo_vehiculo', 'grupo_vehiculo')->ignore($id)],
            'estado_grupo_vehiculo' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'grupo_vehiculo' => 'grupo de vehículo',
            'estado_grupo_vehiculo' => 'estado',
        ];
    }
}
