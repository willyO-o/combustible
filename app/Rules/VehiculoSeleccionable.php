<?php

namespace App\Rules;

use App\Models\User;
use App\Models\Vehiculo;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Vehículo que el usuario puede elegir al REGISTRAR un vale, una carga de
 * combustible o una operación diaria (web y API), con el mismo criterio que
 * las listas de los formularios (ListVehiculosSeleccionablesAction):
 *
 * - Debe estar ACTIVO. Con $permitirInactivo (registro offline de la app)
 *   basta con que exista: pudo capturarse antes de que el vehículo se diera
 *   de baja o de que terminara su asignación.
 * - Un conductor puro sólo puede usar los vehículos que tiene asignados
 *   (User::idsVehiculosAsignados()). Cualquier otro rol o combinación de
 *   roles (administrador, jefe de área, técnico, ...) puede usar todos.
 */
class VehiculoSeleccionable implements ValidationRule
{
    public function __construct(
        protected ?User $user,
        protected bool $permitirInactivo = false,
    ) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $vehiculo = Vehiculo::query()->select(['id', 'estado_vehiculo'])->find($value);

        if (! $vehiculo) {
            $fail('El vehículo seleccionado no existe.');

            return;
        }

        // Registro offline: se capturó antes de sincronizar y para entonces el
        // vehículo pudo darse de baja o terminar su asignación; basta con que
        // exista (mismo criterio que el operario en CargaCombustibleRequest).
        if ($this->permitirInactivo) {
            return;
        }

        if ($vehiculo->estado_vehiculo !== 'ACTIVO') {
            $fail('El vehículo seleccionado no está activo.');

            return;
        }

        if ($this->user?->esConductorPuro() && ! $this->user->idsVehiculosAsignados()->contains($vehiculo->id)) {
            $fail('Sólo puedes registrar con un vehículo que tengas asignado.');
        }
    }
}
