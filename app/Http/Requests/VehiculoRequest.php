<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehiculoRequest extends FormRequest
{
    /**
     * Campos opcionales para asignar el vehículo a un área en el mismo
     * registro (sólo al crear). No son columnas de vehiculo: el controller
     * los separa y se los pasa a AsignarVehiculoAreaAction.
     */
    public const CAMPOS_ASIGNACION_AREA = ['id_area', 'estado_asignacion', 'fecha_culminacion', 'motivo_asignacion'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * El número de chasis se guarda sin espacios en los extremos y en
     * mayúsculas; vacío = null (el campo es opcional).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('chasis')) {
            $chasis = is_string($this->input('chasis')) ? mb_strtoupper(trim($this->input('chasis'))) : $this->input('chasis');

            $this->merge(['chasis' => $chasis === '' ? null : $chasis]);
        }
    }

    public function rules(): array
    {
        $vehiculo = $this->route('vehiculo');

        return [
            'nro_placa' => ['nullable', 'string', 'max:20', Rule::unique('vehiculo', 'nro_placa')->ignore($vehiculo?->id)->whereNull('deleted_at')],
            // Opcional y único como la placa (ignorando los eliminados).
            'chasis' => ['nullable', 'string', 'max:50', Rule::unique('vehiculo', 'chasis')->ignore($vehiculo?->id)->whereNull('deleted_at')],
            'codigo' => ['nullable', 'string', 'max:50'],
            'anio' => ['nullable', 'string', 'max:4', 'regex:/^\d{4}$/'],
            'marca' => ['nullable', 'string', 'max:50'],
            'modelo' => ['nullable', 'string', 'max:50'],
            'estado_vehiculo' => ['required', Rule::in(['ACTIVO', 'RETIRADO', 'VENDIDO'])],
            'tipo_medicion' => ['required', Rule::in(['kilometraje', 'horometro'])],
            'id_tipo_combustible' => ['required', 'integer', 'exists:tipo_combustible,id'],
            'id_tipo_vehiculo' => ['required', 'integer', 'exists:tipo_vehiculo,id'],
            // Obligatoria al registrar (POST); al editar (PUT/_method spoofed)
            // es opcional: sin archivo nuevo VehiculoController::update()
            // conserva la actual. "nullable" y no sólo "sometimes": el
            // formulario (Inertia, multipart) SIEMPRE envía la clave, vacía si
            // no se cambió la foto -> llega null y "image" la rechazaba.
            'fotografia' => [$this->method() === 'POST' ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:2048'],
            'capacidad' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'capacidad_unidad' => ['nullable', 'string', 'max:20', 'required_with:capacidad'],
            ...$this->reglasAsignacionArea(),
        ];
    }

    /**
     * Asignación de área opcional al registrar (mismas reglas que
     * VehiculoAreaRequest). Sin área elegida, el resto de campos se ignora.
     * Sólo quien puede asignar áreas (User::puedeAsignarAreaAVehiculos()).
     * Al editar no aplica: el área se cambia desde el modal de asignación.
     *
     * @return array<string, array<mixed>>
     */
    private function reglasAsignacionArea(): array
    {
        if ($this->method() !== 'POST') {
            return array_fill_keys(self::CAMPOS_ASIGNACION_AREA, ['exclude']);
        }

        $sinArea = Rule::excludeIf(fn () => blank($this->input('id_area')));

        return [
            'id_area' => [
                'bail',
                'nullable',
                Rule::prohibitedIf(fn () => ! $this->user()?->puedeAsignarAreaAVehiculos()),
                'integer',
                Rule::exists('area', 'id')->where('estado_area', 'ACTIVO'),
            ],
            'estado_asignacion' => [$sinArea, 'required', Rule::in(['ACTIVO', 'PROVISIONAL'])],
            'fecha_culminacion' => [$sinArea, 'nullable', 'date', 'after_or_equal:today', 'prohibited_unless:estado_asignacion,PROVISIONAL'],
            'motivo_asignacion' => [$sinArea, 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // El año es opcional: el mensaje genérico ("formato inválido")
            // hacía pensar que era obligatorio.
            'anio.regex' => 'El año debe tener 4 dígitos (ej. 2020) o dejarse vacío.',
            'anio.max' => 'El año debe tener 4 dígitos (ej. 2020) o dejarse vacío.',
            'id_area.prohibited' => 'No tienes permiso para asignar vehículos a un área.',
            'id_area.exists' => 'El área seleccionada no existe o no está activa.',
            'fecha_culminacion.prohibited_unless' => 'La fecha de finalización sólo aplica para asignaciones provisionales.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nro_placa' => 'número de placa',
            'chasis' => 'número de chasis',
            'codigo' => 'código contable',
            'anio' => 'año',
            'marca' => 'marca',
            'modelo' => 'modelo',
            'estado_vehiculo' => 'estado',
            'tipo_medicion' => 'tipo de medición',
            'id_tipo_combustible' => 'tipo de combustible',
            'id_tipo_vehiculo' => 'tipo de vehículo',
            'fotografia' => 'fotografía',
            'capacidad' => 'capacidad',
            'capacidad_unidad' => 'unidad de capacidad',
            'id_area' => 'área',
            'estado_asignacion' => 'tipo de asignación',
            'fecha_culminacion' => 'fecha de finalización',
            'motivo_asignacion' => 'motivo',
        ];
    }
}
