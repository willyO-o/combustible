<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $vale = $this->route('vale');

        return [
            'litros'       => ['required', 'numeric', 'min:0.01', 'max:9999.99'],
            'precio'       => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            'id_vehiculo'  => ['required', 'integer', 'exists:vehiculo,id'],
            'id_conductor' => ['required', 'integer', 'exists:conductor,id'],
            'id_grifo'     => ['required', 'integer', 'exists:grifo,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nro_vale'      => 'número de vale',
            'fecha_emision' => 'fecha de emisión',
            'litros'        => 'litros',
            'precio'        => 'precio',
            'id_vehiculo'   => 'vehículo',
            'id_conductor'  => 'conductor',
            'id_grifo'      => 'estación de servicio',
            'estado_vale'   => 'estado',
            'id_tipo_combustible' => 'tipo de combustible',
        ];
    }
}
