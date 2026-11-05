<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Cambio del modo de la operación diaria por el encargado (la ruta ya exige
 * SoloEncargadoDeRegularizacion):
 * - activar: el modo estricto rige desde hoy.
 * - programar: rige desde `fecha` (un día futuro).
 * - desactivar: vuelve al modo transición (también cancela una programación).
 */
class ModoRegularizacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->esEncargadoDeRegularizacion() ?? false;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'accion' => ['required', Rule::in(['activar', 'programar', 'desactivar'])],
            'fecha' => ['exclude_unless:accion,programar', 'required', 'date', 'after:today'],
        ];
    }

    /**
     * Día desde el que rige el modo estricto según la acción, o null para
     * volver al modo transición.
     */
    public function estrictaDesde(): ?Carbon
    {
        return match ($this->validated('accion')) {
            'activar' => today(),
            'programar' => Carbon::parse($this->validated('fecha'))->startOfDay(),
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'accion' => 'acción',
            'fecha' => 'fecha de inicio del modo estricto',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha.after' => 'La fecha programada debe ser posterior a hoy. Para empezar hoy, usa "Activar ahora".',
        ];
    }
}
