<?php

namespace App\Rules;

use App\Models\CargaCombustible;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;
use Illuminate\Translation\PotentiallyTranslatedString;

class GreaterThanPreviousReading implements ValidationRule
{
    protected mixed $idVehiculo;

    protected string $type; // 'kilometraje' u 'horometro'

    /**
     * $hastaFecha (opcional): sólo se comparan las cargas hechas hasta esa
     * fecha. Lo usa la operación diaria para poder regularizar jornadas
     * pasadas: su lectura de entonces es menor a la de cargas posteriores.
     */
    public function __construct(mixed $idVehiculo, string $type, protected mixed $hastaFecha = null)
    {
        $this->idVehiculo = $idVehiculo;
        $this->type = strtolower($type);
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        //
        if (! $this->idVehiculo || is_null($value)) {
            return;
        }

        $hasta = $this->fechaLimite();

        // Busca el último registro para este vehículo (hasta $hasta, si se indicó)
        $lastRecord = CargaCombustible::where('id_vehiculo', $this->idVehiculo)
            ->when($hasta, fn ($query) => $query->where('fecha_carga', '<=', $hasta)->latest('fecha_carga'))
            ->latest('id')
            ->first();

        if ($lastRecord) {
            $previousValue = $lastRecord->{$this->type};

            if ($previousValue !== null && $value < $previousValue) {
                $etiqueta = $this->type === 'kilometraje' ? 'kilometraje' : 'horómetro';
                $unidad = $this->type === 'kilometraje' ? 'km' : 'hrs';

                $fail("El {$etiqueta} no puede ser menor al último registrado para este vehículo ({$previousValue} {$unidad}).");
            }
        }
    }

    private function fechaLimite(): ?Carbon
    {
        if (blank($this->hastaFecha)) {
            return null;
        }

        try {
            return Carbon::parse($this->hastaFecha);
        } catch (\Throwable) {
            return null;
        }
    }
}
