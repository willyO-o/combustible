<?php

namespace App\Http\Requests;

use App\Models\Vehiculo;
use App\Rules\GreaterThanPreviousReading;
use App\Rules\VehiculoSeleccionable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CargaCombustibleRequest extends FormRequest
{
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

    public function rules(): array
    {
        // Al editar, el vale queda bloqueado en el formulario y se reenvía sin cambios;
        // en ese caso no se exige que siga PENDIENTE (ya fue marcado USADO al registrar la carga).
        $carga = $this->route('carga');
        $valeSinCambios = $carga
            && $this->filled('id_vale')
            && (int) $this->input('id_vale') === (int) $carga->id_vale;

        // La app Flutter permite registrar cargas sin conexión y las sincroniza
        // después: para ese momento el vale ya pudo haber sido usado o vencer,
        // así que is_offline (sólo se respeta viniendo de la API) omite la
        // exigencia de que el vale siga PENDIENTE y vigente.
        $esRegistroOffline = $this->is('api/*') && $this->boolean('is_offline');

        // Al registrar sin vale, quien no es conductor puro (aunque también
        // tenga el rol conductor) elige libremente el operario: cualquiera
        // ACTIVO, no sólo los asignados al vehículo. Con vale el conductor sale
        // del vale y el conductor puro siempre es él mismo (ambos se fuerzan en
        // CreateCargaCombustibleAction), así que ahí no se valida lo enviado.
        // Al editar el conductor no cambia (CargaCombustibleController::update()).
        $eligeConductor = $this->isMethod('POST')
            && ! $this->filled('id_vale')
            && ! $this->user()?->esConductorPuro();

        return [
            'fecha_carga' => [$esRegistroOffline ? 'required' : 'nullable', 'date'],
            // litros y precio son requeridos solo cuando id_vale es null, de lo contrario no se requieren y se ignoran
            'litros' => [Rule::requiredIf(function () {
                return ! $this->filled('id_vale');
            }), 'nullable', 'numeric', 'min:0.01', 'max:9999.99'],
            'precio' => [Rule::requiredIf(function () {
                return ! $this->filled('id_vale');
            }), 'nullable', 'numeric', 'min:0.01', 'max:99999.99'],
            // requerir kilometraje si el id_vehiculo tiene tipo_medicion = KILOMETRAJE, de lo contrario permitir null
            'kilometraje' => [Rule::requiredIf(function () {
                $vehiculo = Vehiculo::find($this->id_vehiculo);

                return $vehiculo && $vehiculo->tipo_medicion === 'kilometraje';
            }), 'nullable', 'numeric', 'min:0', new GreaterThanPreviousReading($this->id_vehiculo, 'kilometraje')],
            'horometro' => [Rule::requiredIf(function () {
                $vehiculo = Vehiculo::find($this->id_vehiculo);

                return $vehiculo && $vehiculo->tipo_medicion === 'horometro';
            }), 'nullable', 'numeric', 'min:0', new GreaterThanPreviousReading($this->id_vehiculo, 'horometro')],
            // Al registrar sin vale: vehículo ACTIVO (cualquiera para todo rol
            // o combinación de roles; el conductor puro, sólo los suyos). Un
            // registro offline basta con que exista. Con vale el vehículo sale
            // del vale (CreateCargaCombustibleAction) y al editar no cambia.
            'id_vehiculo' => $this->isMethod('POST') && ! $this->filled('id_vale')
                ? ['required', 'integer', new VehiculoSeleccionable($this->user(), permitirInactivo: $esRegistroOffline)]
                : ['required', 'integer', 'exists:vehiculo,id'],
            // 'id_grifo' => ['required', 'integer', 'exists:grifo,id'],
            // 'id_tipo_combustible' => ['required', 'integer', 'exists:tipo_combustible,id'],
            // Obligatorio sólo en la web (el formulario lo pide); la API lo
            // mantiene opcional para no romper la app, pero si llega se valida.
            // Un registro offline pudo capturarse con un operario que después
            // se inactivó: ahí basta con que exista.
            'id_conductor' => $eligeConductor
                ? [
                    Rule::requiredIf(! $this->is('api/*')),
                    'nullable',
                    'integer',
                    $esRegistroOffline
                        ? Rule::exists('conductor', 'id')
                        : Rule::exists('conductor', 'id')->where('estado_conductor', 'ACTIVO')->whereNull('deleted_at'),
                ]
                : ['nullable'],
            'id_vale' => [
                'nullable',
                'integer',
                'exists:vale,id',
                Rule::when($this->filled('id_vale') && ! $valeSinCambios && ! $esRegistroOffline, [
                    Rule::exists('vale', 'id')->where(function ($query) {
                        $query->where('estado_vale', 'PENDIENTE')
                            ->where('id_vehiculo', $this->id_vehiculo)
                            ->where('fecha_vencimiento', '>', now());
                    }),
                ]),
            ],
            'nro_factura' => ['nullable', 'string', 'max:50'],
            'concepto' => ['nullable', 'string', 'max:255'],
            'tipo_carga' => ['required', Rule::in(['VALE', 'PREPAGO'])],
            'estado_carga' => ['nullable', 'string', Rule::in(['REGISTRADO', 'VERIFICADO', 'ANULADO'])],
            // Sólo tiene efecto en la API (ver $esRegistroOffline arriba): marca
            // que la carga se capturó sin conexión en la app y se sincroniza después.
            'is_offline' => ['sometimes', 'boolean'],
            // Respaldos (archivos planos para FormData)
        ];
    }

    public function attributes(): array
    {
        return [
            'fecha_carga' => 'fecha de carga',
            'litros' => 'litros',
            'precio' => 'precio',
            'kilometraje' => 'kilometraje',
            'id_vehiculo' => 'vehículo',
            'id_grifo' => 'grifo',
            'id_tipo_combustible' => 'tipo de combustible',
            'id_conductor' => 'conductor',
            'id_vale' => 'vale',
            'nro_factura' => 'número de factura',
            'concepto' => 'a utilizarse en',
            'tipo_carga' => 'tipo de carga',
            'estado_carga' => 'estado',
            'is_offline' => 'registro sin conexión',
        ];
    }

    public function messages(): array
    {
        return [
            // kilometraje/horometro.greater_than_previous_reading NO se define
            // aquí a propósito: GreaterThanPreviousReading ya arma su propio
            // mensaje con el último valor registrado (ver su $fail()), y una
            // entrada estática aquí lo pisaría dejando al usuario sin saber
            // cuál es el valor mínimo esperado.
            'id_conductor.required' => 'Debe seleccionar el conductor.',
            'id_conductor.exists' => 'El conductor seleccionado no existe o no está activo.',
            'kilometraje.required_if' => 'El kilometraje es obligatorio para vehículos con medición por kilometraje.',
            'horometro.required_if' => 'El horómetro es obligatorio para vehículos con medición por horómetro.',
        ];
    }
}
