<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use App\Rules\GreaterThanPreviousReading;
use Illuminate\Foundation\Http\FormRequest;

class OperacionStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            "id_vehiculo" => "required|exists:vehiculo,id",
            "turno" => "required|in:DIA,NOCHE",
            "fecha_inicio" => "required|date",
            "fecha_fin" => "required|date|after:fecha_inicio",
            "kilometraje_inicio" => [Rule::requiredIf(function () {
                                            $vehiculo = \App\Models\Vehiculo::find($this->id_vehiculo);
                                            return $vehiculo && $vehiculo->tipo_medicion === 'kilometraje';
                                        }),'nullable' ,'numeric', 'min:0', new GreaterThanPreviousReading($this->id_vehiculo, 'kilometraje')],
            "kilometraje_fin" => [Rule::requiredIf(fn() => $this->kilometraje_inicio !== null), 'nullable', 'numeric', 'min:0', 'gt:kilometraje_inicio'],
            "horometro_inicio" => [Rule::requiredIf(function () {
                                            $vehiculo = \App\Models\Vehiculo::find($this->id_vehiculo);
                                            return $vehiculo && $vehiculo->tipo_medicion === 'horometro';
                                        }), 'nullable', 'numeric', 'min:0', new GreaterThanPreviousReading($this->id_vehiculo, 'horometro')],
            "horometro_fin" => [Rule::requiredIf(fn() => $this->horometro_inicio !== null), 'nullable', 'numeric', 'min:0', 'gt:horometro_inicio'],
            "horas_trabajadas" => 'required|numeric|min:0',
            "observaciones" => 'nullable|string|min:10',
            "notificar_observaciones" => 'required|boolean',
            'actividades_realizadas' => 'required|array|min:1',
            'actividades_realizadas.*.actividad' => 'required|string|min:3',
            'actividades_realizadas.*.lugar' => 'required_without:actividades_realizadas.*.origen|nullable|string',
            'actividades_realizadas.*.origen' => 'required_without:actividades_realizadas.*.actividad|nullable|string',
            'actividades_realizadas.*.destino' => 'required_without:actividades_realizadas.*.actividad|nullable|string',
            'actividades_realizadas.*.cantidad' => 'required|numeric|min:1',
            'actividades_realizadas.*.unidad_medida' => 'required|string|min:1',
            'actividades_realizadas.*.hora_inicio' => 'required|date_format:H:i',
            'actividades_realizadas.*.hora_fin' => 'required|date_format:H:i|after:actividades_realizadas.*.hora_inicio',

        ];
    }
}
