<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Rules\GreaterThanPreviousReading;

class CargaCombustibleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_carga'         => ['required', 'date'],
            'litros'              => ['required', 'numeric', 'min:0.01', 'max:9999.99'],
            'precio'              => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            // requerir kilometraje si el id_vehiculo tiene tipo_medicion = KILOMETRAJE, de lo contrario permitir null
            'kilometraje'         => [Rule::requiredIf(function () {
                                            $vehiculo = \App\Models\Vehiculo::find($this->id_vehiculo);
                                            return $vehiculo && $vehiculo->tipo_medicion === 'kilometraje';
                                        }),'nullable' ,'numeric', 'min:0', new GreaterThanPreviousReading($this->id_vehiculo, 'kilometraje')],
            'horometro'           => [Rule::requiredIf(function () {
                                            $vehiculo = \App\Models\Vehiculo::find($this->id_vehiculo);
                                            return $vehiculo && $vehiculo->tipo_medicion === 'horometro';
                                        }), 'nullable', 'numeric', 'min:0', new GreaterThanPreviousReading($this->id_vehiculo, 'horometro')],
            'id_vehiculo'         => ['required', 'integer', 'exists:vehiculo,id'],
            'id_grifo'            => ['required', 'integer', 'exists:grifo,id'],
            'id_tipo_combustible' => ['required', 'integer', 'exists:tipo_combustible,id'],
            'id_conductor'        => [auth()->user()->hasRole('conductor') ? 'required' : 'nullable', 'integer', 'exists:conductor,id'],
            'id_vale'             => ['nullable', 'integer', 'exists:vale,id'],
            'nro_factura'         => ['nullable', 'string', 'max:50'],
            'tipo_carga'          => ['required', Rule::in(['VALE', 'PREPAGO'])],
            'estado_carga'        => ['nullable', 'string', Rule::in(['REGISTRADO', 'VERIFICADO', 'ANULADO'])],
            // Respaldos (archivos planos para FormData)
            'respaldo_count'      => ['nullable', 'integer', 'min:0', 'max:5'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $count = (int) $this->input('respaldo_count', 0);
            for ($i = 0; $i < $count; $i++) {
                if ($this->hasFile("respaldo_archivo_{$i}")) {
                    $file = $this->file("respaldo_archivo_{$i}");
                    if (!in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])) {
                        $v->errors()->add("respaldo_archivo_{$i}", 'Solo se permiten imágenes (JPG, PNG, WEBP) o PDF.');
                    }
                    if ($file->getSize() > 5 * 1024 * 1024) {
                        $v->errors()->add("respaldo_archivo_{$i}", 'El archivo no puede superar 5 MB.');
                    }
                }
            }

            // Si hay vale seleccionado, tipo_carga debe ser VALE
            if ($this->filled('id_vale') && $this->input('tipo_carga') !== 'VALE') {
                $v->errors()->add('tipo_carga', 'Si se selecciona un vale, el tipo de carga debe ser VALE.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'fecha_carga'         => 'fecha de carga',
            'litros'              => 'litros',
            'precio'              => 'precio',
            'kilometraje'         => 'kilometraje',
            'id_vehiculo'         => 'vehículo',
            'id_grifo'            => 'grifo',
            'id_tipo_combustible' => 'tipo de combustible',
            'id_conductor'        => 'conductor',
            'id_vale'             => 'vale',
            'nro_factura'         => 'número de factura',
            'tipo_carga'          => 'tipo de carga',
            'estado_carga'        => 'estado',
        ];
    }

    public function messages(): array
    {
        return [
            'kilometraje.greater_than_previous_reading' => 'El kilometraje debe ser mayor al último registrado para este vehículo.',
            'horometro.greater_than_previous_reading'   => 'El horómetro debe ser mayor al último registrado para este vehículo.',
            'kilometraje.required_if' => 'El kilometraje es obligatorio para vehículos con medición por kilometraje.',
            'horometro.required_if'   => 'El horómetro es obligatorio para vehículos con medición por horómetro.',
        ];
    }
}
