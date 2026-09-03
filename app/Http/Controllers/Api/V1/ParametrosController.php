<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\Grifo;
use App\Models\Material;
use App\Models\Repuesto;
use App\Models\TipoCombustible;
use App\Models\TipoMantenimiento;
use App\Models\VehiculoExterno;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParametrosController extends Controller
{
    //

    public function index(Request $request): JsonResponse
    {
        $parametros = [
            'api_version' => '1.2.0',
            'app_name' => config('app.name'),
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug'),
            'app_url' => config('app.url'),
            'timezone' => config('app.timezone'),
            'locale' => config('app.locale'),
            'url_logo' => asset('images/logo.png'),
        ];

        return response()->json($parametros);
    }

    public function colecciones(Request $request): JsonResponse
    {
        $persona = $request->user()->persona;

        // Una persona puede no ser conductor (p. ej. un jefe de área) y puede
        // tener ambos roles a la vez. Cada fuente de vehículos se deriva de los
        // registros relacionados, no del rol, tolerando que cualquiera falte.
        $conductor = $persona?->conductor;

        $areasACargo = $persona
            ? $persona->encargadoAreas()->with('vehiculosActivos')->get()
            : collect();

        // Vehículos propios del conductor autenticado (si lo es) unificados con
        // los de las áreas que administra como jefe de área. unique('id') evita
        // duplicar el vehículo que un jefe de área también conduce.
        $vehiculos = EloquentCollection::make(
            ($conductor ? $conductor->asignacionesActivas : collect())
                ->merge($areasACargo->flatMap(fn ($area) => $area->vehiculosActivos))
                ->unique('id')
                ->values()
                ->all()
        );

        // Conductor actualmente asignado a cada vehículo (titular ACTIVO), para
        // que un jefe de área pueda autocompletar `id_conductor` al emitir un
        // vale desde la app (mismo dato que `meta.id_conductor` del buscador de
        // vehículos del módulo web). Se carga en bloque para evitar N+1.
        if ($vehiculos->isNotEmpty()) {
            $vehiculos->load('conductorAsignado.persona:id,nombres,paterno,materno,ci');
        }

        $vehiculos = $vehiculos->map(function ($vehiculo) {
            $conductorAsignado = $vehiculo->conductorAsignado;

            return [
                'id' => $vehiculo->id,
                'uuid' => $vehiculo->uuid,
                'nro_placa' => $vehiculo->nro_placa,
                'codigo' => $vehiculo->codigo,
                'anio' => $vehiculo->anio,
                'marca' => $vehiculo->marca,
                'modelo' => $vehiculo->modelo,
                'estado_vehiculo' => $vehiculo->estado_vehiculo,
                'id_tipo_combustible' => $vehiculo->id_tipo_combustible,
                'id_tipo_vehiculo' => $vehiculo->id_tipo_vehiculo,
                'url_fotografia' => $vehiculo->url_fotografia,
                'tipo_medicion' => $vehiculo->tipo_medicion,
                'id_conductor' => $conductorAsignado?->id,
                'conductor_asignado' => $conductorAsignado ? [
                    'id' => $conductorAsignado->id,
                    'nombre_completo' => trim("{$conductorAsignado->persona?->nombres} {$conductorAsignado->persona?->paterno} {$conductorAsignado->persona?->materno}"),
                    'ci' => $conductorAsignado->persona?->ci,
                ] : null,
            ];
        });

        // Áreas para sugerir actividades: las que el conductor cubre por sus
        // asignaciones más las que administra como jefe de área.
        $areas = collect($conductor ? $conductor->areas()->pluck('id') : [])
            ->merge($areasACargo->pluck('id'))
            ->unique()
            ->values()
            ->all();

        $actividadesSugeridas = Actividad::select('id', 'nombre_actividad', 'unidad_medida')->whereIn('id_area', $areas)->get();

        $asignaciones = [
            'vehiculos' => $vehiculos,
            'estaciones_servicio' => Grifo::where('estado_grifo', 'ACTIVO')->get(),
            'tipos_combustible' => TipoCombustible::where('estado_tipo_combustible', 'ACTIVO')->get(),
            'cargas_combustible' => [
                'tipos_carga' => ['VALE', 'PREPAGO'],
                'tipos_respaldo_digital' => ['FACTURA', 'NOTA', 'COMPROBANTE', 'OTRO'],
                'estado_carga' => ['REGISTRADO', 'VERIFICADO', 'ANULADO'],
            ],
            'solicitudes_mantenimiento' => [
                'tipos_mantenimiento' => ['PREVENTIVO', 'CORRECTIVO'],
                'estado' => ['PENDIENTE', 'APROBADA', 'RECHAZADA', 'ANULADA'],
            ],
            'ordenes_trabajo' => [
                'estados_orden' => ['PENDIENTE', 'EN_EJECUCION', 'CULMINADO', 'CANCELADO', 'VERIFICADO'],
                // Colección de estados de la orden de trabajo con su detalle
                // (etiqueta y descripción) para mostrar en la app.
                'estados' => [
                    ['value' => 'PENDIENTE', 'label' => 'Pendiente', 'descripcion' => 'La orden fue emitida y está a la espera de que el técnico inicie el trabajo.'],
                    ['value' => 'EN_EJECUCION', 'label' => 'En ejecución', 'descripcion' => 'El técnico inició el mantenimiento y está registrando el detalle del trabajo realizado.'],
                    ['value' => 'CULMINADO', 'label' => 'Culminado', 'descripcion' => 'El técnico terminó el trabajo y registró las lecturas finales. El detalle queda congelado.'],
                    ['value' => 'VERIFICADO', 'label' => 'Verificado', 'descripcion' => 'El usuario que emitió la orden validó la ejecución. Estado final.'],
                    ['value' => 'CANCELADO', 'label' => 'Cancelado', 'descripcion' => 'La orden fue anulada y no se ejecutará.'],
                ],
                // Acciones que puede realizar el técnico y a qué estado llevan
                // la orden (desde qué estados están permitidas).
                'acciones_tecnico' => [
                    ['accion' => 'iniciar', 'metodo' => 'PATCH', 'ruta' => 'ordenes-trabajo/{orden}/iniciar', 'desde' => ['PENDIENTE'], 'estado_resultante' => 'EN_EJECUCION'],
                    ['accion' => 'agregar_detalle', 'metodo' => 'POST', 'ruta' => 'ordenes-trabajo/{orden}/detalles', 'desde' => ['PENDIENTE', 'EN_EJECUCION'], 'estado_resultante' => null],
                    ['accion' => 'editar_detalle', 'metodo' => 'PATCH', 'ruta' => 'ordenes-trabajo/{orden}/detalles/{detalle}', 'desde' => ['PENDIENTE', 'EN_EJECUCION'], 'estado_resultante' => null],
                    ['accion' => 'eliminar_detalle', 'metodo' => 'DELETE', 'ruta' => 'ordenes-trabajo/{orden}/detalles/{detalle}', 'desde' => ['PENDIENTE', 'EN_EJECUCION'], 'estado_resultante' => null],
                    ['accion' => 'culminar', 'metodo' => 'POST', 'ruta' => 'ordenes-trabajo/{orden}/culminar', 'desde' => ['EN_EJECUCION'], 'estado_resultante' => 'CULMINADO'],
                ],
                // Catálogos para armar cada ítem del detalle de trabajo
                // (POST /ordenes-trabajo/{orden}/detalles): el tipo de
                // mantenimiento aplicado y, opcionalmente, el repuesto usado.
                // Sólo tipos de mantenimiento de taller: el detalle de la orden
                // de trabajo es mantenimiento de taller (los de 'operacion_diaria'
                // se registran en otro flujo).
                'tipos_mantenimiento' => TipoMantenimiento::where('estado_tipo_mantenimiento', 'ACTIVO')
                    ->where('ambito', 'taller')
                    ->orderBy('tipo_mantenimiento')
                    ->get(['id', 'tipo_mantenimiento']),
                'repuestos' => Repuesto::where('estado_repuesto', 'ACTIVO')
                    ->orderBy('nombre_repuesto')
                    ->get(['id', 'nombre_repuesto', 'codigo_repuesto', 'unidad_medida', 'stock_actual']),
            ],
            'operaciones_diarias' => [
                'actividades_sugeridas' => $actividadesSugeridas,
                // Controles de mantenimiento que se pueden registrar en una
                // operación diaria (ámbito operacion_diaria, activos). Según
                // `tipo_valor`: `cantidad` -> se envía `valor` (en `unidad_medida`),
                // `booleano` -> se envía `realizado` (SI/NO). Los tipos de ámbito
                // taller no aplican aquí (se usan en órdenes de trabajo).
                'tipos_mantenimiento' => TipoMantenimiento::where('estado_tipo_mantenimiento', 'ACTIVO')
                    ->where('ambito', 'operacion_diaria')
                    ->orderBy('tipo_mantenimiento')
                    ->get(['id', 'tipo_mantenimiento', 'tipo_valor', 'unidad_medida']),
            ],
            'control_cargas' => [
                // Catálogo de materiales transportados en cada viaje (cola,
                // broza, concentrado, etc., según lo que maneje cada
                // operación minera). También se puede consultar/registrar
                // uno nuevo directamente en /materiales.
                'materiales' => Material::orderBy('material')->get(['id', 'material']),
                // Vehículos externos (no pertenecen a la flota propia) que
                // transportan el material. También se puede consultar/
                // registrar uno nuevo directamente en /vehiculos-externos.
                'vehiculos_externos' => VehiculoExterno::orderBy('nro_placa')->get(['id', 'nro_placa', 'propietario']),
                'estados_carga' => ['ABIERTA', 'CERRADA', 'PAGADA'],
            ],
        ];

        return response()->json([
            'data' => $asignaciones,
        ]);
    }
}
