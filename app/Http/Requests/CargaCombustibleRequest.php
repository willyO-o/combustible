<?php

namespace App\Http\Requests;

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
        return [
            'fecha_carga'         => ['required', 'date'],
            'litros'              => ['required', 'numeric', 'min:0.01', 'max:9999.99'],
            'precio'              => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            'kilometraje'         => ['nullable', 'integer', 'min:0'],
            'id_vehiculo'         => ['required', 'integer', 'exists:vehiculo,id'],
            'id_grifo'            => ['required', 'integer', 'exists:grifo,id'],
            'id_tipo_combustible' => ['required', 'integer', 'exists:tipo_combustible,id'],
            'id_conductor'        => ['required', 'integer', 'exists:conductor,id'],
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
}
