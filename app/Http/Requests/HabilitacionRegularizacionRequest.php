<?php

namespace App\Http\Requests;

use App\Actions\OperacionDiaria\ListUsuariosParaRegularizacionAction;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Habilitación temporal para regularizar operaciones diarias de días
 * pasados, la otorga el encargado (la ruta ya exige
 * SoloEncargadoDeRegularizacion).
 */
class HabilitacionRegularizacionRequest extends FormRequest
{
    /**
     * Vigencias rápidas que ofrece el formulario (además de "personalizada").
     */
    public const VIGENCIAS = ['fin_del_dia', '24h', '48h', '72h', 'personalizada'];

    /** Una vigencia personalizada no puede pasar de estos días. */
    public const MAX_DIAS_VIGENCIA = 30;

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
            'id_usuario' => ['required', 'integer', $this->reglaUsuarioHabilitable()],
            // Sólo días pasados: hoy (y la noche de ayer) ya se pueden registrar sin habilitación.
            'fecha_desde' => ['required', 'date', 'before:today'],
            'fecha_hasta' => ['required', 'date', 'after_or_equal:fecha_desde', 'before:today'],
            'vigencia' => ['required', Rule::in(self::VIGENCIAS)],
            'vigente_hasta' => [
                'exclude_unless:vigencia,personalizada',
                'required',
                'date',
                'after:now',
                'before_or_equal:'.now()->addDays(self::MAX_DIAS_VIGENCIA)->toDateTimeString(),
            ],
            'motivo' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * Hasta cuándo puede usar el usuario la habilitación.
     */
    public function vigenteHasta(): Carbon
    {
        return match ($this->validated('vigencia')) {
            'fin_del_dia' => now()->endOfDay(),
            '24h' => now()->addHours(24),
            '48h' => now()->addHours(48),
            '72h' => now()->addHours(72),
            default => Carbon::parse($this->validated('vigente_hasta')),
        };
    }

    /**
     * El usuario debe estar activo y poder registrar operaciones diarias
     * (misma lista que ofrece el formulario).
     */
    private function reglaUsuarioHabilitable(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_numeric($value) || app(ListUsuariosParaRegularizacionAction::class)->execute((int) $value)->isEmpty()) {
                $fail('El usuario seleccionado no está activo o no puede registrar operaciones diarias.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'id_usuario' => 'usuario',
            'fecha_desde' => 'día inicial',
            'fecha_hasta' => 'día final',
            'vigencia' => 'vigencia',
            'vigente_hasta' => 'vigente hasta',
            'motivo' => 'motivo',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha_desde.before' => 'Sólo se habilitan días pasados: hoy (y la noche de ayer) ya se pueden registrar.',
            'fecha_hasta.before' => 'Sólo se habilitan días pasados: hoy (y la noche de ayer) ya se pueden registrar.',
            'vigente_hasta.after' => 'La vigencia debe terminar después de este momento.',
            'vigente_hasta.before_or_equal' => 'La vigencia no puede superar '.self::MAX_DIAS_VIGENCIA.' días.',
        ];
    }
}
