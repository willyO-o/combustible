<?php

namespace App\Http\Requests;

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
            'fecha_nacimiento' => ['nullable', 'date'],
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
        ];
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
        ];
    }

    public function messages(): array
    {
        return [
            'documentos.*.tipo_documento.required' => 'Cada documento debe indicar su tipo.',
            'documentos.*.id.exists' => 'Uno de los documentos enviados no pertenece a este conductor.',
        ];
    }
}
