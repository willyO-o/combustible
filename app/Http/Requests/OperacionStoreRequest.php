<?php

namespace App\Http\Requests;

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
            'actividades_realizadas.*.cantidad.required' => 'La cantidad es obligatoria.',
            'actividades_realizadas.*.unidad_medida.required' => 'La unidad de medida es obligatoria.',
            'actividades_realizadas.*.hora_inicio.required' => 'La hora de inicio es obligatoria.',
            'actividades_realizadas.*.hora_fin.required' => 'La hora de fin es obligatoria.',
            'actividades_realizadas.*.hora_fin.after' => 'La hora de fin debe ser posterior a la hora de inicio.',
            'mantenimientos.*.id_tipo_mantenimiento.exists' => 'El control de mantenimiento seleccionado no es válido para operación diaria.',
            'mantenimientos.*.valor.numeric' => 'El valor del control de mantenimiento debe ser numérico.',
        ];
    }
}
