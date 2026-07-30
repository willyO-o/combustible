<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use App\Models\CargaCombustible;

class GreaterThanPreviousReading implements ValidationRule
{

    protected mixed $idVehiculo;
    protected string $type; // 'kilometraje' u 'horometro'

    public function __construct(mixed $idVehiculo, string $type)
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
     if (!$this->idVehiculo || is_null($value)) {
            return;
        }

        // Busca el último registro para este vehículo
        $lastRecord = CargaCombustible::where('id_vehiculo', $this->idVehiculo)
            ->latest('id')
            ->first();

        if ($lastRecord) {
            $previousValue = $lastRecord->{$this->type};

            if ($previousValue !== null && $value < $previousValue) {
                $unidad = $this->type === 'kilometraje' ? 'km' : 'hrs';

                $fail("El {$this->type} no puede ser inferior al último registrado ({$previousValue} {$unidad}).");
            }
        }
    }
}
