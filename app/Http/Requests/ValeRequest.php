<?php

namespace App\Http\Requests;

use App\Models\VehiculoArea;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

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
            'litros' => ['required', 'numeric', 'min:0.01', 'max:9999.99'],
            'precio' => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            'id_vehiculo' => ['required', 'integer', 'exists:vehiculo,id', $this->reglaVehiculoDeAreaDeJefe()],
            'id_conductor' => ['required', 'integer', 'exists:conductor,id'],
            'id_grifo' => ['required', 'integer', 'exists:grifo,id'],
        ];
    }

    /**
     * Al emitir (crear) un vale, un jefe de área sólo puede elegir un
     * vehículo con asignación de área activa/provisional a alguna de sus
     * áreas a cargo. No se reevalúa al editar: el vehículo del vale ya está
     * fijo y bloqueado en el formulario, y podría haberse reasignado de área
     * después de emitido sin que eso deba bloquear otras correcciones.
     */
    private function reglaVehiculoDeAreaDeJefe(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! $this->isMethod('POST')) {
                return;
            }

            $user = $this->user();

            if (! $user || ! $user->hasRole('jefe-area') || $user->hasAnyRole(['super-admin', 'administrador'])) {
                return;
            }

            $areas = $user->persona?->encargadoAreas()->pluck('id_area')->toArray() ?? [];

            $asignado = VehiculoArea::where('id_vehiculo', $value)
                ->whereIn('id_area', $areas)
                ->whereIn('estado_asignacion', ['ACTIVO', 'PROVISIONAL'])
                ->where(function ($query) {
                    $query->whereNull('fecha_culminacion')
                        ->orWhere('fecha_culminacion', '>', now());
                })
                ->exists();

            if (! $asignado) {
                $fail('Sólo puede emitir vales para vehículos asignados a su área.');
            }
        };
    }

    public function attributes(): array
    {
        return [
            'nro_vale' => 'número de vale',
            'fecha_emision' => 'fecha de emisión',
            'litros' => 'litros',
            'precio' => 'precio',
            'id_vehiculo' => 'vehículo',
            'id_conductor' => 'conductor',
            'id_grifo' => 'estación de servicio',
            'estado_vale' => 'estado',
            'id_tipo_combustible' => 'tipo de combustible',
        ];
    }
}
