<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RepuestoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $repuesto = $this->route('repuesto');

        return [
            'nombre_repuesto' => ['required', 'string', 'max:200'],
            'codigo_repuesto' => ['required', 'string', 'max:100', Rule::unique('repuesto', 'codigo_repuesto')->ignore($repuesto?->id)],
            'descripcion_repuesto' => ['nullable', 'string', 'max:1000'],
            'unidad_medida' => ['required', Rule::in(['UNIDAD', 'LITRO', 'KILOGRAMO', 'METRO', 'JUEGO', 'CAJA', 'BOLSA', 'PAQUETE', 'OTRO'])],
            'stock_actual' => ['required', 'integer', 'min:0'],
            'estado_repuesto' => ['required', Rule::in(['ACTIVO', 'INACTIVO', 'AGOTADO'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre_repuesto' => 'nombre del repuesto',
            'codigo_repuesto' => 'código',
            'descripcion_repuesto' => 'descripción',
            'unidad_medida' => 'unidad de medida',
            'stock_actual' => 'stock actual',
            'estado_repuesto' => 'estado',
        ];
    }
}
