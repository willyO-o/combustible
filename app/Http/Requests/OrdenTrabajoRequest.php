<?php

namespace App\Http\Requests;

use App\Models\Vehiculo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrdenTrabajoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_vehiculo' => ['required', 'exists:vehiculo,id'],
            'id_conductor' => ['nullable', 'exists:conductor,id'],
            'id_solicitud_mantenimiento' => ['nullable', 'exists:solicitud_mantenimiento,id'],
            'id_taller' => ['nullable', 'exists:taller,id'],
            'id_usuario_ejecuta' => ['required', 'exists:users,id'],
            'tipo_mantenimiento' => ['required', 'in:PREVENTIVO,CORRECTIVO'],
            'nota_emisor' => ['nullable', 'string', 'max:1000'],
            'kilometraje_actual' => [
                Rule::requiredIf(function () {
                    $vehiculo = Vehiculo::find($this->id_vehiculo);

                    return $vehiculo && $vehiculo->tipo_medicion === 'kilometraje';
                }),
                'nullable',
                'integer',
                'min:0',
            ],
            'horometro_actual' => [
                Rule::requiredIf(function () {
                    $vehiculo = Vehiculo::find($this->id_vehiculo);

                    return $vehiculo && $vehiculo->tipo_medicion === 'horometro';
                }),
                'nullable',
                'integer',
                'min:0',
            ],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_vehiculo.required' => 'Debe seleccionar un vehículo.',
            'id_usuario_ejecuta.required' => 'Debe asignar un responsable de ejecución.',
            'tipo_mantenimiento.required' => 'Debe indicar si es preventivo o correctivo.',
        ];
    }
}
