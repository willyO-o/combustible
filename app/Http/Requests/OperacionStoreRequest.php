<?php

namespace App\Http\Requests;

use App\Models\MantenimientoOperacionDiaria;
use App\Models\OperacionDiaria;
use App\Models\Vehiculo;
use App\Models\VehiculoArea;
use App\Rules\GreaterThanPreviousReading;
use App\Rules\VehiculoSeleccionable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class OperacionStoreRequest extends FormRequest
{
    /**
     * Días hacia atrás que puede registrar un usuario sin rol de gestión
     * (1 = hoy o ayer). Fechas anteriores sólo con User::puedeRegularizarOperaciones().
     */
    public const DIAS_ATRAS_SIN_REGULARIZAR = 1;

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
            if (! $validator->errors()->has('fecha_inicio')) {
                $this->validarFechaInicio($validator);
            }
        });

        // Topes de lectura por jornada del tipo de vehículo (ver
        // validarTopeDeLectura()).
        $validator->after(function (Validator $validator) {
            $this->validarTopeDeLectura($validator, 'kilometraje');
            $this->validarTopeDeLectura($validator, 'horometro');
        });

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
     * Reglas de la fecha de inicio (web y API comparten esta request):
     * - Una sola operación diaria por vehículo y por día (excluye la propia
     *   operación al editar).
     * - jefe-area/administrador/super-admin pueden usar cualquier fecha (a
     *   pedido del usuario, para regularizar jornadas no registradas).
     * - El resto, sólo hoy o ayer (ayer cubre el turno noche y el registro al
     *   día siguiente), nunca una fecha futura. Al editar, si el día no cambia
     *   no se exige este límite: la operación ya existía.
     */
    /**
     * La lectura de una jornada no puede superar el tope del tipo de
     * vehículo (TipoVehiculo::km_maximo_jornada / horometro_maximo_jornada,
     * el que corresponde a su tipo_medicion). Frena errores de tipeo como un
     * punto decimal olvidado: 5460.7 -> 55101 serían 49.640 km en un día.
     *
     * Sólo se evalúa si las lecturas ya pasaron sus reglas (numéricas y
     * final > inicial). Tipo sin tope (null) = sin control. Aplica igual en
     * alta y edición, web y API (esta request es compartida).
     */
    private function validarTopeDeLectura(Validator $validator, string $tipoMedicion): void
    {
        $campoInicio = "{$tipoMedicion}_inicio";
        $campoFin = "{$tipoMedicion}_fin";

        if ($validator->errors()->hasAny([$campoInicio, $campoFin])) {
            return;
        }

        $inicio = $this->input($campoInicio);
        $fin = $this->input($campoFin);

        if (! is_numeric($inicio) || ! is_numeric($fin) || (float) $fin <= (float) $inicio) {
            return;
        }

        // En PUT/PATCH prepareForValidation() ya fijó id_vehiculo al de la operación.
        $vehiculo = Vehiculo::with('tipoVehiculo:id,tipo_vehiculo,km_maximo_jornada,horometro_maximo_jornada')
            ->find($this->input('id_vehiculo'));

        if (! $vehiculo || $vehiculo->tipo_medicion !== $tipoMedicion) {
            return;
        }

        $tope = $vehiculo->tipoVehiculo?->topeJornada($tipoMedicion);
        $diferencia = round((float) $fin - (float) $inicio, 2);

        if ($tope === null || $diferencia <= $tope) {
            return;
        }

        $tipo = $vehiculo->tipoVehiculo->tipo_vehiculo;

        $validator->errors()->add($campoFin, $tipoMedicion === 'kilometraje'
            ? "El recorrido de la jornada ({$this->formatearLectura($diferencia)} km) supera el máximo de {$this->formatearLectura($tope)} km para {$tipo}. Revisa el kilometraje final: ¿falta un punto decimal?"
            : "Las horas de horómetro de la jornada ({$this->formatearLectura($diferencia)} h) superan el máximo de {$this->formatearLectura($tope)} h para {$tipo}. Revisa el horómetro final: ¿falta un punto decimal?");
    }

    /**
     * 49640.3 -> "49.640,3"; 1000 -> "1.000" (sin decimales si es entero).
     */
    private function formatearLectura(float $valor): string
    {
        $decimales = fmod($valor, 1.0) == 0.0 ? 0 : 1;

        return number_format($valor, $decimales, ',', '.');
    }

    private function validarFechaInicio(Validator $validator): void
    {
        $fechaInicio = Carbon::parse($this->input('fecha_inicio'));
        $operacion = $this->route('operacionDiaria');
        $idVehiculo = (int) ($this->input('id_vehiculo') ?? $operacion?->id_vehiculo);

        $mismoDiaQueLaOperacion = $operacion?->fecha_inicio?->isSameDay($fechaInicio) ?? false;
        $fechaMinima = now()->subDays(self::DIAS_ATRAS_SIN_REGULARIZAR)->startOfDay();

        if (! $mismoDiaQueLaOperacion && ! $this->user()?->puedeRegularizarOperaciones()) {
            if ($fechaInicio->greaterThan(now()->endOfDay())) {
                $validator->errors()->add('fecha_inicio', 'La fecha de inicio no puede ser posterior a hoy.');

                return;
            }

            if ($fechaInicio->lessThan($fechaMinima)) {
                $validator->errors()->add(
                    'fecha_inicio',
                    'Sólo puedes registrar operaciones de hoy o de ayer. Para regularizar una fecha anterior, solicítalo a tu jefe de área.'
                );

                return;
            }
        }

        if ($idVehiculo && ($existente = OperacionDiaria::delVehiculoEnElDia($idVehiculo, $fechaInicio, $operacion?->id))) {
            $validator->errors()->add(
                'fecha_inicio',
                "El vehículo ya tiene una operación diaria registrada el {$fechaInicio->format('d/m/Y')} (N° {$existente->nro_operacion})."
            );
        }
    }

    /**
     * Cualquier usuario puede elegir cualquier vehículo activo, pero la
     * operación toma el área vigente del vehículo (id_area NOT NULL): sin
     * área no se puede registrar. Antes esto llegaba como un error genérico
     * de la Action (AreaNoAsignadaException); ahora es un error del campo.
     * Sólo al crear: al editar el vehículo y el área ya están fijos.
     */
    private function reglaVehiculoConArea(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($this->method() !== 'POST' || ! $value) {
                return;
            }

            $tieneArea = VehiculoArea::query()
                ->where('id_vehiculo', $value)
                ->whereIn('estado_asignacion', ['ACTIVO', 'PROVISIONAL'])
                ->where(fn ($query) => $query->whereNull('fecha_culminacion')->orWhere('fecha_culminacion', '>', now()))
                ->exists();

            if (! $tieneArea) {
                $fail('El vehículo no tiene un área asignada: asígnalo a un área antes de registrar su operación.');
            }
        };
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
            // Al registrar: vehículo ACTIVO para todo rol o combinación de
            // roles; el conductor puro, sólo los suyos (VehiculoSeleccionable).
            // Al editar el vehículo queda fijo (prepareForValidation()).
            'id_vehiculo' => $this->method() === 'POST'
                ? ['required', 'integer', new VehiculoSeleccionable($this->user()), $this->reglaVehiculoConArea()]
                : ['sometimes', 'exists:vehiculo,id', $this->reglaVehiculoConArea()],
            // Los roles de gestión eligen el operario entre TODOS los
            // conductores ACTIVO (ya no sólo los asignados al vehículo). Es
            // obligatorio al crear salvo para el conductor puro, que no elige:
            // el controller le asigna el suyo (User::esConductorPuro()).
            // Al editar basta con que exista: conservar el operario original
            // es válido aunque ya no esté activo; un cambio lo valida
            // UpdateOperacionDiariaAction.
            'id_conductor' => $this->method() === 'POST'
                ? [Rule::requiredIf(fn () => ! $this->user()?->esConductorPuro()), 'nullable', Rule::exists('conductor', 'id')->where('estado_conductor', 'ACTIVO')->whereNull('deleted_at')]
                : ['sometimes', 'nullable', 'exists:conductor,id'],
            'turno' => 'required|in:DIA,NOCHE',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_inicio',
            'kilometraje_inicio' => [$this->method() === 'POST' ? Rule::requiredIf(function () {
                $vehiculo = Vehiculo::find($this->id_vehiculo);

                return $vehiculo && $vehiculo->tipo_medicion === 'kilometraje';
            }) : 'sometimes', 'nullable', 'numeric', 'min:0', new GreaterThanPreviousReading($this->id_vehiculo, 'kilometraje', $this->input('fecha_inicio'))],
            'kilometraje_fin' => [$this->method() === 'POST' ? Rule::requiredIf(fn () => $this->kilometraje_inicio !== null) : 'sometimes', 'nullable', 'numeric', 'min:0', 'gt:kilometraje_inicio'],
            'horometro_inicio' => [$this->method() === 'POST' ? Rule::requiredIf(function () {
                $vehiculo = Vehiculo::find($this->id_vehiculo);

                return $vehiculo && $vehiculo->tipo_medicion === 'horometro';
            }) : 'sometimes', 'nullable', 'numeric', 'min:0', new GreaterThanPreviousReading($this->id_vehiculo, 'horometro', $this->input('fecha_inicio'))],
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
            // Cantidad y unidad de medida son opcionales (a pedido del usuario).
            'actividades_realizadas.*.cantidad' => 'nullable|numeric|min:1',
            'actividades_realizadas.*.unidad_medida' => 'nullable|string|max:30',
            // hora_inicio/hora_fin ya no se capturan: si un cliente antiguo
            // las envía se ignoran. El id (del registro ya guardado, al
            // editar) sólo sirve para conservar las horas históricas, ver
            // SincronizarActividadesRealizadasAction.
            'actividades_realizadas.*.id' => 'nullable|integer',

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
            'id_conductor.required' => 'Debe seleccionar el operario.',
            'id_conductor.exists' => 'El operario seleccionado no existe o no está activo.',
            'actividades_realizadas.required' => 'Debe agregar al menos una actividad realizada.',
            'actividades_realizadas.*.actividad.required' => 'La actividad es obligatoria.',
            'actividades_realizadas.*.lugar.required' => 'El lugar es obligatorio.',
            'actividades_realizadas.*.origen.required' => 'El origen es obligatorio.',
            'actividades_realizadas.*.destino.required' => 'El destino es obligatorio.',
            'actividades_realizadas.*.id_material.exists' => 'El material seleccionado no es válido.',
            'mantenimientos.*.id_tipo_mantenimiento.exists' => 'El control de mantenimiento seleccionado no es válido para operación diaria.',
            'mantenimientos.*.valor.numeric' => 'El valor del control de mantenimiento debe ser numérico.',
            'mantenimientos.*.evidencia.image' => 'La evidencia debe ser una imagen.',
        ];
    }
}
