<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TipoMantenimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // La clave del binding es 'tipoMantenimiento' (Route::resource con
        // ->parameters(['tipos-mantenimiento' => 'tipoMantenimiento'])), no la
        // versión snake_case — ver .ai/rules/requests.md.
        $id = $this->route('tipoMantenimiento')?->id;

        $ambito = $this->input('ambito');
        $esOperacionDiaria = $ambito === 'operacion_diaria';

        return [
            'tipo_mantenimiento' => [
                'required', 'string', 'max:150',
                // La unicidad se acota al ámbito: un mismo nombre puede existir
                // como tipo de taller y como tipo de operación diaria.
                Rule::unique('tipo_mantenimiento', 'tipo_mantenimiento')
                    ->where(fn ($query) => $query->where('ambito', $ambito ?: 'taller'))
                    ->ignore($id),
            ],
            'estado_tipo_mantenimiento' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
            'ambito' => ['required', Rule::in(['taller', 'operacion_diaria'])],
            // tipo_valor / unidad_medida sólo aplican al ámbito operación diaria.
            // unidad_medida además sólo se pide cuando el valor es una cantidad
            // (un valor booleano no lleva unidad).
            'tipo_valor' => [
                $esOperacionDiaria ? 'required' : 'nullable',
                Rule::in(['cantidad', 'booleano']),
            ],
            'unidad_medida' => [
                Rule::requiredIf($esOperacionDiaria && $this->input('tipo_valor') === 'cantidad'),
                'nullable', 'string', 'max:100',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'tipo_mantenimiento' => 'tipo de mantenimiento',
            'estado_tipo_mantenimiento' => 'estado',
            'ambito' => 'ámbito',
            'tipo_valor' => 'tipo de valor',
            'unidad_medida' => 'unidad de medida',
        ];
    }
}
