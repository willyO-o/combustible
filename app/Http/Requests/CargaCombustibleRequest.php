<?php

namespace App\Http\Requests;

use App\Models\Vehiculo;
use App\Rules\GreaterThanPreviousReading;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CargaCombustibleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Al editar, el vale queda bloqueado en el formulario y se reenvía sin cambios;
        // en ese caso no se exige que siga PENDIENTE (ya fue marcado USADO al registrar la carga).
        $carga = $this->route('carga');
        $valeSinCambios = $carga
            && $this->filled('id_vale')
            && (int) $this->input('id_vale') === (int) $carga->id_vale;

        return [
            'fecha_carga' => ['required', 'date'],
            // litros y precio son requeridos solo cuando id_vale es null, de lo contrario no se requieren y se ignoran
            'litros' => [Rule::requiredIf(function () {
                return ! $this->filled('id_vale');
            }), 'nullable', 'numeric', 'min:0.01', 'max:9999.99'],
            'precio' => [Rule::requiredIf(function () {
                return ! $this->filled('id_vale');
            }), 'nullable', 'numeric', 'min:0.01', 'max:99999.99'],
            // requerir kilometraje si el id_vehiculo tiene tipo_medicion = KILOMETRAJE, de lo contrario permitir null
            'kilometraje' => [Rule::requiredIf(function () {
                $vehiculo = Vehiculo::find($this->id_vehiculo);

                return $vehiculo && $vehiculo->tipo_medicion === 'kilometraje';
            }), 'nullable', 'numeric', 'min:0', new GreaterThanPreviousReading($this->id_vehiculo, 'kilometraje')],
            'horometro' => [Rule::requiredIf(function () {
                $vehiculo = Vehiculo::find($this->id_vehiculo);

                return $vehiculo && $vehiculo->tipo_medicion === 'horometro';
            }), 'nullable', 'numeric', 'min:0', new GreaterThanPreviousReading($this->id_vehiculo, 'horometro')],
            'id_vehiculo' => ['required', 'integer', 'exists:vehiculo,id'],
            // 'id_grifo' => ['required', 'integer', 'exists:grifo,id'],
            // 'id_tipo_combustible' => ['required', 'integer', 'exists:tipo_combustible,id'],
            // 'id_conductor' => [$this->user()->hasRole('conductor') ? 'required' : 'nullable', 'integer', 'exists:conductor,id'],
            'id_vale' => ['nullable', 'integer', 'exists:vale,id',
                Rule::when($this->filled('id_vale') && ! $valeSinCambios, [
                    Rule::exists('vale', 'id')->where(function ($query) {
                        $query->where('estado_vale', 'PENDIENTE')
                            ->where('id_vehiculo', $this->id_vehiculo)
                            ->where('fecha_vencimiento', '>', now());
                    }),
                ]),
            ],
            'nro_factura' => ['nullable', 'string', 'max:50'],
            'tipo_carga' => ['required', Rule::in(['VALE', 'PREPAGO'])],
            'estado_carga' => ['nullable', 'string', Rule::in(['REGISTRADO', 'VERIFICADO', 'ANULADO'])],
            // Respaldos (archivos planos para FormData)
        ];
    }

    public function attributes(): array
    {
        return [
            'fecha_carga' => 'fecha de carga',
            'litros' => 'litros',
            'precio' => 'precio',
            'kilometraje' => 'kilometraje',
            'id_vehiculo' => 'vehículo',
            'id_grifo' => 'grifo',
            'id_tipo_combustible' => 'tipo de combustible',
            'id_conductor' => 'conductor',
            'id_vale' => 'vale',
            'nro_factura' => 'número de factura',
            'tipo_carga' => 'tipo de carga',
            'estado_carga' => 'estado',
        ];
    }

    public function messages(): array
    {
        return [
            'kilometraje.greater_than_previous_reading' => 'El kilometraje debe ser mayor al último registrado para este vehículo.',
            'horometro.greater_than_previous_reading' => 'El horómetro debe ser mayor al último registrado para este vehículo.',
            'kilometraje.required_if' => 'El kilometraje es obligatorio para vehículos con medición por kilometraje.',
            'horometro.required_if' => 'El horómetro es obligatorio para vehículos con medición por horómetro.',
        ];
    }
}
