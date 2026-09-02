<?php

namespace App\Http\Requests;

use App\Models\Vehiculo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConductorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // verificar conductores eliminados ya que se esta usando softDeletes, para que no se pueda crear un conductor con el mismo carnet de identidad que uno eliminado
        $conductor = $this->route('conductor');

        return [
            'ci' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('persona', 'ci')->ignore($conductor?->id)],
            'nombres' => ['sometimes', 'required', 'string', 'max:150'],
            'paterno' => ['sometimes', 'nullable', 'string', 'max:150', 'required_without:materno'],
            'materno' => ['sometimes', 'nullable', 'string', 'max:150', 'required_without:paterno'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:2048'],
            'celular' => ['nullable', 'string', 'max:20'],
            'direccion' => ['nullable', 'string', 'max:250'],
            // fecha nacimiento si se ingreso debe ser anterior a 17 años
            'fecha_nacimiento' => ['nullable', 'date', Rule::date()->before(now()->subYears(17))],
            'estado_conductor' => ['required', Rule::in(['ACTIVO', 'INACTIVO', 'RETIRADO'])],

            // Documentos del conductor: adicionales y opcionales.
            'documentos' => ['sometimes', 'array'],
            'documentos.*.id' => [
                'nullable',
                'integer',
                Rule::exists('documento_conductor', 'id')->where(fn ($query) => $query->where('id_conductor', $conductor?->id)),
            ],
            'documentos.*.tipo_documento' => ['required', Rule::in(['LICENCIA_DE_CONDUCIR', 'CI', 'CERTIFICADO_MEDICO', 'OTRO'])],
            'documentos.*.numero_documento' => ['nullable', 'string', 'max:100'],
            'documentos.*.categoria' => ['nullable', 'string', 'max:10'],
            'documentos.*.fecha_emision' => ['nullable', 'date'],
            'documentos.*.fecha_vencimiento' => ['nullable', 'date'],
            'documentos.*.archivo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
            'documentos.*.estado_documento' => ['nullable', Rule::in(['VIGENTE', 'VENCIDO', 'OBSERVADO'])],
            'documentos.*.observacion' => ['nullable', 'string', 'max:1000'],

            // La asignación de un vehículo sólo se ofrece al REGISTRAR el
            // operario. Al editar, la (re)asignación se hace desde el listado
            // de operarios, por lo que estas reglas no aplican en update().
            ...($conductor ? [] : $this->reglasAsignacionVehiculo()),
        ];
    }

    /**
     * Reglas de la asignación opcional de un vehículo al registrar el
     * operario. Todo el bloque sólo se exige cuando se envía un id_vehiculo.
     *
     * @return array<string, mixed>
     */
    private function reglasAsignacionVehiculo(): array
    {
        return [
            'id_vehiculo' => ['nullable', 'exists:vehiculo,id'],
            'estado_asignacion' => ['nullable', 'required_with:id_vehiculo', Rule::in(['ACTIVO', 'PROVISIONAL'])],
            'fecha_asignacion' => ['nullable', 'date', 'before_or_equal:today'],
            'fecha_culminacion' => ['nullable', 'date', 'after_or_equal:today', 'prohibited_unless:estado_asignacion,PROVISIONAL'],
            'detalle' => ['nullable', 'string', 'max:1000'],
            'kilometraje_inicial' => [
                Rule::requiredIf(fn () => $this->vehiculoAsignadoSeMideEn('kilometraje')),
                'nullable', 'numeric', 'min:0',
            ],
            'horometro_inicial' => [
                Rule::requiredIf(fn () => $this->vehiculoAsignadoSeMideEn('horometro')),
                'nullable', 'numeric', 'min:0',
            ],
        ];
    }

    /**
     * True si se envió un vehículo a asignar y su tipo de medición coincide
     * con el indicado (para exigir su lectura inicial correspondiente).
     */
    private function vehiculoAsignadoSeMideEn(string $tipoMedicion): bool
    {
        if (! $this->filled('id_vehiculo')) {
            return false;
        }

        return Vehiculo::where('id', $this->input('id_vehiculo'))->value('tipo_medicion') === $tipoMedicion;
    }

    public function attributes(): array
    {
        return [
            'ci' => 'carnet de identidad',
            'nombres' => 'nombres',
            'paterno' => 'apellido paterno',
            'materno' => 'apellido materno',
            'foto' => 'foto',
            'celular' => 'celular',
            'direccion' => 'dirección',
            'fecha_nacimiento' => 'fecha de nacimiento',
            'estado_conductor' => 'estado',

            'documentos.*.tipo_documento' => 'tipo de documento',
            'documentos.*.numero_documento' => 'número de documento',
            'documentos.*.categoria' => 'categoría',
            'documentos.*.fecha_emision' => 'fecha de emisión',
            'documentos.*.fecha_vencimiento' => 'fecha de vencimiento',
            'documentos.*.archivo' => 'archivo',
            'documentos.*.estado_documento' => 'estado del documento',
            'documentos.*.observacion' => 'observación',

            'id_vehiculo' => 'vehículo',
            'estado_asignacion' => 'tipo de asignación',
            'fecha_asignacion' => 'fecha de asignación',
            'fecha_culminacion' => 'fecha de finalización',
            'detalle' => 'motivo de la asignación',
            'kilometraje_inicial' => 'kilometraje inicial',
            'horometro_inicial' => 'horómetro inicial',
        ];
    }

    public function messages(): array
    {
        return [
            'documentos.*.tipo_documento.required' => 'Cada documento debe indicar su tipo.',
            'documentos.*.id.exists' => 'Uno de los documentos enviados no pertenece a este conductor.',
            'fecha_nacimiento.before' => 'El conductor debe tener al menos 17 años.',

            'id_vehiculo.exists' => 'El vehículo seleccionado no existe.',
            'estado_asignacion.required_with' => 'Indique el tipo de asignación del vehículo.',
            'fecha_culminacion.prohibited_unless' => 'La fecha de finalización sólo aplica a asignaciones provisionales.',
            'kilometraje_inicial.required' => 'El kilometraje inicial es obligatorio para este vehículo.',
            'horometro_inicial.required' => 'El horómetro inicial es obligatorio para este vehículo.',
        ];
    }
}
