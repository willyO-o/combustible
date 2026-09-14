<?php

namespace App\Http\Requests;

use App\Models\MantenimientoOperacionDiaria;
use App\Models\Vehiculo;
use App\Rules\GreaterThanPreviousReading;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class OperacionStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El vehículo de una operación no se puede cambiar al editar: sus lecturas
     * (km/horómetro) y actividades dependen de su tipo_medicion. Se descarta
     * cualquier `id_vehiculo` entrante y se conserva el de la operación.
     */
    protected function prepareForValidation(): void
    {
        $operacion = $this->route('operacionDiaria');

        if ($operacion) {
            $this->merge(['id_vehiculo' => $operacion->id_vehiculo]);
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->expectsJson()) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Los datos enviados no son válidos.',
                'errors' => $validator->errors(),
            ], 422));
        }

        parent::failedValidation($validator);
    }

    /**
     * La evidencia fotográfica es obligatoria para todo control de
     * mantenimiento que se está registrando: `valor` cargado (tipo
     * `cantidad`), o `realizado = SI` (tipo `booleano`). Marcar `NO` no
     * exige evidencia — no hay nada que fotografiar de algo que no se hizo.
     *
     * No se valida como regla normal en rules() porque una regla de archivo
     * (`image`, `mimes`, o un closure común) no se ejecuta cuando el campo
     * llega ausente — y "ausente" es exactamente el caso que hay que
     * detectar aquí (sin archivo nuevo). Se resuelve con un `after()`, mismo
     * patrón que EncargadoAreaRequest.
     *
     * Al editar, si el control ya tenía evidencia guardada y no se pidió
     * `eliminar_evidencia`, se conserva la existente sin exigir un archivo
     * nuevo (mismo criterio que "sometimes" ya usado en el resto de esta
     * request para PUT/PATCH).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $operacion = $this->route('operacionDiaria');

            foreach ($this->input('mantenimientos', []) as $idx => $fila) {
                $valor = $fila['valor'] ?? null;
                $realizado = $fila['realizado'] ?? null;
                $eliminarEvidencia = $fila['eliminar_evidencia'] ?? false;

                $requiereEvidencia = ($valor !== null && $valor !== '') || $realizado === 'SI';

                if (! $requiereEvidencia || $this->hasFile("mantenimientos.$idx.evidencia")) {
                    continue;
                }

                if ($operacion && ! $eliminarEvidencia) {
                    $yaTieneEvidencia = MantenimientoOperacionDiaria::query()
                        ->where('id_operacion_diaria', $operacion->id)
                        ->where('id_tipo_mantenimiento', $fila['id_tipo_mantenimiento'] ?? null)
                        ->whereNotNull('evidencia')
                        ->exists();

                    if ($yaTieneEvidencia) {
                        continue;
                    }
                }

                $validator->errors()->add(
                    "mantenimientos.$idx.evidencia",
                    'La evidencia fotográfica es obligatoria para este control de mantenimiento.'
                );
            }
        });
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // dd($this->method());
        return [
            'id_vehiculo' => [$this->method() === 'POST' ? 'required' : 'sometimes', 'exists:vehiculo,id'],
            // El combo de conductor sólo se muestra en el formulario a quien
            // no tiene el rol conductor (un conductor siempre es él mismo);
            // para esos roles es obligatorio al crear, ya que un vehículo
            // puede tener varios conductores asignados a la vez (titular +
            // provisionales). El controller lo completa automáticamente con
            // el conductor autenticado cuando sí tiene el rol conductor.
            'id_conductor' => [$this->method() === 'POST' ? Rule::requiredIf(fn () => ! $this->user()?->hasRole('conductor')) : 'sometimes', 'nullable', 'exists:conductor,id'],
            'turno' => 'required|in:DIA,NOCHE',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_inicio',
            'kilometraje_inicio' => [$this->method() === 'POST' ? Rule::requiredIf(function () {
                $vehiculo = Vehiculo::find($this->id_vehiculo);

                return $vehiculo && $vehiculo->tipo_medicion === 'kilometraje';
            }) : 'sometimes', 'nullable', 'numeric', 'min:0', new GreaterThanPreviousReading($this->id_vehiculo, 'kilometraje')],
            'kilometraje_fin' => [$this->method() === 'POST' ? Rule::requiredIf(fn () => $this->kilometraje_inicio !== null) : 'sometimes', 'nullable', 'numeric', 'min:0', 'gt:kilometraje_inicio'],
            'horometro_inicio' => [$this->method() === 'POST' ? Rule::requiredIf(function () {
                $vehiculo = Vehiculo::find($this->id_vehiculo);

                return $vehiculo && $vehiculo->tipo_medicion === 'horometro';
            }) : 'sometimes', 'nullable', 'numeric', 'min:0', new GreaterThanPreviousReading($this->id_vehiculo, 'horometro')],
            'horometro_fin' => [$this->method() === 'POST' ? Rule::requiredIf(fn () => $this->horometro_inicio !== null) : 'sometimes', 'nullable', 'numeric', 'min:0', 'gt:horometro_inicio'],
            // "horas_trabajadas" => 'required|numeric|min:0', se calcula automáticamente a partir de fecha_inicio y fecha_fin
            'observaciones' => 'nullable|string|min:10',
            'notificar_observaciones' => 'required|boolean',
            'actividades_realizadas' => 'required|array|min:1',
            'actividades_realizadas.*.actividad' => 'required|string|min:3',
            'actividades_realizadas.*.lugar' => [Rule::requiredIf(function () {
                $vehiculo = Vehiculo::find($this->id_vehiculo);

                return $vehiculo && $vehiculo->tipo_medicion === 'horometro';
            }), 'nullable', 'string'],
            'actividades_realizadas.*.origen' => [Rule::requiredIf(function () {
                $vehiculo = Vehiculo::find($this->id_vehiculo);

                return $vehiculo && $vehiculo->tipo_medicion === 'kilometraje';
            }), 'nullable', 'string'],
            'actividades_realizadas.*.destino' => [Rule::requiredIf(function () {
                $vehiculo = Vehiculo::find($this->id_vehiculo);

                return $vehiculo && $vehiculo->tipo_medicion === 'kilometraje';
            }), 'nullable', 'string'],
            // Material trasladado: sólo se captura para vehículos con medición
            // por kilometraje (viajes y traslados). Opcional — no todo viaje
            // mueve material (traslado de personal, retorno en vacío, etc.).
            'actividades_realizadas.*.id_material' => ['nullable', 'integer', Rule::exists('material', 'id')],
            'actividades_realizadas.*.cantidad' => 'required|numeric|min:1',
            'actividades_realizadas.*.unidad_medida' => 'required|string|min:1',
            'actividades_realizadas.*.hora_inicio' => 'required|date_format:H:i',
            'actividades_realizadas.*.hora_fin' => 'required|date_format:H:i',

            // Controles de mantenimiento de operación diaria: opcionales, no
            // todos aplican a cada operación. Los tipos deben ser de ámbito
            // operacion_diaria (los de taller se usan en órdenes de trabajo).
            'mantenimientos' => 'nullable|array',
            'mantenimientos.*.id_tipo_mantenimiento' => ['required', 'integer', Rule::exists('tipo_mantenimiento', 'id')->where('ambito', 'operacion_diaria')],
            'mantenimientos.*.valor' => 'nullable|numeric|min:0',
            'mantenimientos.*.realizado' => 'nullable|in:SI,NO',
            // Evidencia fotográfica opcional de lo realizado en ese control
            // (se convierte a webp en el servidor, ver
            // SincronizarMantenimientosOperacionAction). Sin archivo nuevo se
            // conserva la ya guardada; eliminar_evidencia la borra sin subir
            // una nueva.
            'mantenimientos.*.evidencia' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:8192'],
            'mantenimientos.*.eliminar_evidencia' => ['nullable', 'boolean'],

        ];
    }

    public function messages(): array
    {
        return [
            'id_conductor.required' => 'Debe seleccionar el conductor asignado al vehículo.',
            'actividades_realizadas.required' => 'Debe agregar al menos una actividad realizada.',
            'actividades_realizadas.*.actividad.required' => 'La actividad es obligatoria.',
            'actividades_realizadas.*.lugar.required_without' => 'El lugar es obligatorio.',
            'actividades_realizadas.*.origen.required_without' => 'El origen es obligatorio.',
            'actividades_realizadas.*.destino.required_without' => 'El destino es obligatorio.',
            'actividades_realizadas.*.id_material.exists' => 'El material seleccionado no es válido.',
            'actividades_realizadas.*.cantidad.required' => 'La cantidad es obligatoria.',
            'actividades_realizadas.*.unidad_medida.required' => 'La unidad de medida es obligatoria.',
            'actividades_realizadas.*.hora_inicio.required' => 'La hora de inicio es obligatoria.',
            'actividades_realizadas.*.hora_fin.required' => 'La hora de fin es obligatoria.',
            'actividades_realizadas.*.hora_fin.after' => 'La hora de fin debe ser posterior a la hora de inicio.',
            'mantenimientos.*.id_tipo_mantenimiento.exists' => 'El control de mantenimiento seleccionado no es válido para operación diaria.',
            'mantenimientos.*.valor.numeric' => 'El valor del control de mantenimiento debe ser numérico.',
            'mantenimientos.*.evidencia.image' => 'La evidencia debe ser una imagen.',
        ];
    }
}
