<?php

namespace App\Http\Controllers;

use App\Libraries\Reportes;
use App\Models\Area;
use App\Models\TipoCombustible;
use App\Models\Vehiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

/**
 * Reporte de uso de vehículos en operación diaria: por vehículo, cuántas horas
 * se trabajaron y cuánto recorrió (kilometraje u horómetro según su
 * tipo_medicion), agrupado además por tipo de combustible. NO desglosa las
 * actividades de la jornada (eso es un reporte aparte más detallado).
 *
 * Todo el agregado (detalle por vehículo + totales generales) se resuelve en la
 * base de datos con SUM/COUNT/AVG; en PHP sólo se castean escalares.
 */
class OperacionDiariaReportController extends Controller
{
    public function index(Request $request): Response
    {
        $idTipoCombustible = $request->integer('id_tipo_combustible') ?: null;
        $idArea = $request->integer('id_area') ?: null;
        // Selección múltiple para comparar: llega como id_vehiculo[]=1&id_vehiculo[]=2
        // (o ausente/vacío = todos los vehículos).
        $idsVehiculo = $this->idsVehiculo($request);

        // Por defecto "Este mes" (1º del mes -> hoy), igual que el preset por
        // defecto de DateRangeFilter.vue: así la primera carga ya llega
        // filtrada y se evita la doble petición del componente en el cliente.
        $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->format('Y-m-d'));
        $fechaFin = $request->input('fecha_fin', now()->format('Y-m-d'));

        return inertia('Reportes/OperacionDiariaReporte', [
            'vehiculos' => $this->vehiculosParaFiltro($idTipoCombustible, $idArea),
            'tiposCombustible' => $this->tiposCombustibleActivos(),
            'areas' => $this->areasActivas(),
            'datosResumen' => $this->obtenerResumen($fechaInicio, $fechaFin, $idsVehiculo, $idTipoCombustible, $idArea),
            'filtros' => [
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'id_vehiculo' => $idsVehiculo,
                'id_tipo_combustible' => $idTipoCombustible,
                'id_area' => $idArea,
            ],
        ]);
    }

    public function generarPDF(Request $request)
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date',
        ]);

        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');
        $idsVehiculo = $this->idsVehiculo($request);
        $idTipoCombustible = $request->integer('id_tipo_combustible') ?: null;
        $idArea = $request->integer('id_area') ?: null;

        $resumen = $this->obtenerResumen($fechaInicio, $fechaFin, $idsVehiculo, $idTipoCombustible, $idArea);

        // Se resuelven a texto legible aquí (no en Reportes.php) para dejar
        // constancia en el PDF de qué filtro se aplicó, igual que en pantalla.
        $filtrosAplicados = [
            'tipo_combustible' => $idTipoCombustible ? TipoCombustible::find($idTipoCombustible)?->tipo_combustible : null,
            'area' => $idArea ? Area::find($idArea)?->nombre_area : null,
            'vehiculo' => $this->etiquetaVehiculos($idsVehiculo),
        ];

        $contenido = (new Reportes)->generarReporteOperacionDiariaUso(
            $resumen,
            $fechaInicio,
            $fechaFin,
            'S',
            null,
            $filtrosAplicados
        );

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="reporte_operacion_diaria_uso.pdf"',
        ]);
    }

    /* ================================================================
     |  Bitácora detallada de UN vehículo
     |  (una fila por operación diaria + combustible del día +
     |   columnas dinámicas de mantenimiento y material)
     | ================================================================ */

    public function detalle(Request $request): Response
    {
        $idVehiculo = $request->integer('id_vehiculo') ?: null;
        $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->format('Y-m-d'));
        $fechaFin = $request->input('fecha_fin', now()->format('Y-m-d'));

        $vehiculo = $idVehiculo ? $this->vehiculoDetalle($idVehiculo) : null;

        return inertia('Reportes/OperacionDiariaDetalle', [
            'vehiculos' => $this->vehiculosParaFiltro(),
            'vehiculo' => $vehiculo,
            // Sin vehículo elegido no hay nada que consultar: la vista muestra
            // el estado vacío en lugar de forzar una consulta sin sentido.
            'datos' => $vehiculo ? $this->obtenerDetalleVehiculo($vehiculo, $fechaInicio, $fechaFin) : null,
            'filtros' => [
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'id_vehiculo' => $vehiculo?->id,
            ],
        ]);
    }

    public function detallePDF(Request $request)
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date',
            'id_vehiculo' => 'required|integer|exists:vehiculo,id',
        ]);

        $vehiculo = $this->vehiculoDetalle($request->integer('id_vehiculo'));
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');

        $datos = $this->obtenerDetalleVehiculo($vehiculo, $fechaInicio, $fechaFin);

        $contenido = (new Reportes)->generarReporteOperacionDiariaDetalle(
            $vehiculo,
            $datos,
            $fechaInicio,
            $fechaFin,
            'S'
        );

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="bitacora_operacion_'.$vehiculo->codigo.'.pdf"',
        ]);
    }

    private function vehiculoDetalle(int $idVehiculo): ?Vehiculo
    {
        return Vehiculo::select('id', 'codigo', 'nro_placa', 'marca', 'modelo', 'anio', 'tipo_medicion', 'id_tipo_combustible')
            ->with('tipoCombustible:id,tipo_combustible')
            ->find($idVehiculo);
    }

    /**
     * Bitácora mensual de un vehículo: una fila por operación diaria (con su
     * conductor, ya que un vehículo puede tener varias asignaciones), enlazada
     * con la carga de combustible del MISMO día, más columnas dinámicas de
     * mantenimiento (pivote mantenimiento_operacion_diaria) y —sólo si el
     * vehículo mide por kilometraje— de material trasladado (actividad_realizada).
     *
     * Todo el agregado (costo = litros x precio, totales, totales por columna
     * dinámica) se resuelve en SQL; en PHP sólo se reindexan los pivotes por
     * fila (lookups por clave, sin sumatorias iterando). Nº de consultas
     * constante (8), no crece con la cantidad de filas.
     *
     * @return array{tipo_medicion: string, columnas: array, filas: Collection, totales: array}
     */
    private function obtenerDetalleVehiculo(Vehiculo $vehiculo, string $fechaInicio, string $fechaFin): array
    {
        $desde = Carbon::parse($fechaInicio)->startOfDay();
        $hasta = Carbon::parse($fechaFin)->endOfDay();
        $esKilometraje = $vehiculo->tipo_medicion === 'kilometraje';

        // ── Filas base: una por operación diaria del vehículo en el rango.
        // persona.id == conductor.id == operacion_diaria.id_conductor (conductor
        // comparte PK con persona), de ahí el join directo a persona.
        $operaciones = DB::table('operacion_diaria as od')
            ->join('persona as p', 'p.id', '=', 'od.id_conductor')
            ->where('od.id_vehiculo', $vehiculo->id)
            ->whereBetween('od.fecha_inicio', [$desde, $hasta])
            ->orderBy('od.fecha_inicio')
            ->orderBy('od.id')
            ->selectRaw('
                od.id,
                od.nro_operacion,
                od.fecha_inicio,
                od.turno,
                od.observaciones,
                od.horas_trabajadas,
                od.kilometraje_inicio,
                od.kilometraje_fin,
                od.horometro_inicio,
                od.horometro_fin,
                p.nombres,
                p.paterno,
                p.materno
            ')
            ->get();

        $idsOp = $operaciones->pluck('id')->all();

        // ── Carga de combustible agregada por día (para enlazar por fecha).
        $cargasPorDia = DB::table('carga_combustible')
            ->where('id_vehiculo', $vehiculo->id)
            ->whereBetween('fecha_carga', [$desde, $hasta])
            ->groupByRaw('DATE(fecha_carga)')
            ->selectRaw('
                DATE(fecha_carga) as dia,
                ROUND(SUM(litros), 2) as litros,
                ROUND(SUM(litros * precio), 2) as costo,
                ROUND(SUM(litros * precio) / NULLIF(SUM(litros), 0), 2) as precio_unitario,
                MAX(kilometraje) as kilometraje_carga,
                MAX(horometro) as horometro_carga
            ')
            ->get()
            ->keyBy('dia');

        // ── Columnas dinámicas de mantenimiento: sólo los tipos que tienen al
        // menos un registro en las operaciones del rango, con su total.
        $columnasMantenimiento = collect();
        $mantenimientosPorOp = collect();

        if ($idsOp) {
            $columnasMantenimiento = DB::table('mantenimiento_operacion_diaria as m')
                ->join('tipo_mantenimiento as tm', 'tm.id', '=', 'm.id_tipo_mantenimiento')
                ->whereIn('m.id_operacion_diaria', $idsOp)
                ->groupBy('tm.id', 'tm.tipo_mantenimiento', 'tm.tipo_valor', 'tm.unidad_medida')
                ->orderBy('tm.tipo_mantenimiento')
                ->selectRaw("
                    tm.id,
                    tm.tipo_mantenimiento as nombre,
                    tm.tipo_valor,
                    tm.unidad_medida,
                    ROUND(COALESCE(SUM(m.valor), 0), 2) as total_valor,
                    SUM(CASE WHEN m.realizado = 'SI' THEN 1 ELSE 0 END) as total_si
                ")
                ->get();

            $mantenimientosPorOp = DB::table('mantenimiento_operacion_diaria')
                ->whereIn('id_operacion_diaria', $idsOp)
                ->get(['id_operacion_diaria', 'id_tipo_mantenimiento', 'valor', 'realizado'])
                ->groupBy('id_operacion_diaria');
        }

        // ── Columnas dinámicas de material trasladado (sólo vehículos por km).
        $columnasMaterial = collect();
        $materialesPorOp = collect();

        if ($esKilometraje && $idsOp) {
            $columnasMaterial = DB::table('actividad_realizada as ar')
                ->join('material as mat', 'mat.id', '=', 'ar.id_material')
                ->whereIn('ar.id_operacion_diaria', $idsOp)
                ->whereNotNull('ar.id_material')
                ->groupBy('mat.id', 'mat.material')
                ->orderBy('mat.material')
                ->selectRaw('mat.id, mat.material as nombre, ROUND(COALESCE(SUM(ar.cantidad), 0), 2) as total_cantidad')
                ->get();

            $materialesPorOp = DB::table('actividad_realizada')
                ->whereIn('id_operacion_diaria', $idsOp)
                ->whereNotNull('id_material')
                ->groupBy('id_operacion_diaria', 'id_material')
                ->selectRaw('id_operacion_diaria, id_material, ROUND(SUM(cantidad), 2) as cantidad')
                ->get()
                ->groupBy('id_operacion_diaria');
        }

        // ── Totales en SQL. El combustible se totaliza sólo sobre los días con
        // operación y sin duplicar aunque un día tenga turno día y noche.
        $totalesOperacion = DB::table('operacion_diaria')
            ->where('id_vehiculo', $vehiculo->id)
            ->whereBetween('fecha_inicio', [$desde, $hasta])
            ->selectRaw('COUNT(*) as operaciones, ROUND(COALESCE(SUM(horas_trabajadas), 0), 2) as horas_trabajadas')
            ->first();

        $totalesCombustible = DB::table('carga_combustible as cc')
            ->joinSub(
                DB::table('operacion_diaria')
                    ->where('id_vehiculo', $vehiculo->id)
                    ->whereBetween('fecha_inicio', [$desde, $hasta])
                    ->groupByRaw('DATE(fecha_inicio)')
                    ->selectRaw('DATE(fecha_inicio) as dia'),
                'dop',
                fn ($join) => $join->on(DB::raw('DATE(cc.fecha_carga)'), '=', 'dop.dia')
            )
            ->where('cc.id_vehiculo', $vehiculo->id)
            ->whereBetween('cc.fecha_carga', [$desde, $hasta])
            ->selectRaw('ROUND(COALESCE(SUM(cc.litros), 0), 2) as litros, ROUND(COALESCE(SUM(cc.litros * cc.precio), 0), 2) as costo')
            ->first();

        $totalHoras = (float) ($totalesOperacion->horas_trabajadas ?? 0);
        $totalLitros = (float) ($totalesCombustible->litros ?? 0);
        $totalCosto = (float) ($totalesCombustible->costo ?? 0);

        // ── Reshape de las filas: sólo lookups por clave, ninguna sumatoria.
        $diasConCargaMostrada = [];

        $filas = $operaciones->map(function ($op) use (
            $esKilometraje, $cargasPorDia, $mantenimientosPorOp, $materialesPorOp, &$diasConCargaMostrada
        ) {
            $dia = substr((string) $op->fecha_inicio, 0, 10);

            // La carga del día se muestra una sola vez (en la 1ª operación de
            // esa fecha) para que la suma visible de la columna cuadre con el
            // total y no se repita si hay dos turnos el mismo día.
            $carga = null;

            if (! isset($diasConCargaMostrada[$dia]) && $cargasPorDia->has($dia)) {
                $c = $cargasPorDia->get($dia);
                $lecturaCarga = $esKilometraje ? $c->kilometraje_carga : $c->horometro_carga;

                $carga = [
                    'litros' => (float) $c->litros,
                    'precio_unitario' => (float) $c->precio_unitario,
                    'costo' => (float) $c->costo,
                    'lectura_carga' => $lecturaCarga !== null ? (float) $lecturaCarga : null,
                ];
                $diasConCargaMostrada[$dia] = true;
            }

            $mantenimientos = collect($mantenimientosPorOp->get($op->id, []))
                ->keyBy('id_tipo_mantenimiento')
                ->map(fn ($m) => [
                    'valor' => $m->valor !== null ? (float) $m->valor : null,
                    'realizado' => $m->realizado,
                ])
                ->all();

            $materiales = collect($materialesPorOp->get($op->id, []))
                ->keyBy('id_material')
                ->map(fn ($m) => (float) $m->cantidad)
                ->all();

            $lecturaInicio = $esKilometraje ? $op->kilometraje_inicio : $op->horometro_inicio;
            $lecturaFin = $esKilometraje ? $op->kilometraje_fin : $op->horometro_fin;

            return [
                'id' => (int) $op->id,
                'fecha' => $dia,
                'nro_parte' => $op->nro_operacion,
                'turno' => $op->turno,
                'operador' => trim("{$op->nombres} {$op->paterno} {$op->materno}"),
                'lectura_inicio' => $lecturaInicio !== null ? (float) $lecturaInicio : null,
                'lectura_fin' => $lecturaFin !== null ? (float) $lecturaFin : null,
                'horas_trabajadas' => (float) $op->horas_trabajadas,
                'carga' => $carga,
                'mantenimientos' => $mantenimientos,
                'materiales' => $materiales,
                'observaciones' => $op->observaciones,
            ];
        })->values();

        return [
            'tipo_medicion' => $vehiculo->tipo_medicion,
            'columnas' => [
                'mantenimiento' => $columnasMantenimiento->map(fn ($c) => [
                    'id' => (int) $c->id,
                    'nombre' => $c->nombre,
                    'tipo_valor' => $c->tipo_valor,
                    'unidad_medida' => $c->unidad_medida,
                    'total' => $c->tipo_valor === 'booleano'
                        ? (int) $c->total_si
                        : round((float) $c->total_valor, 2),
                ])->values(),
                'material' => $columnasMaterial->map(fn ($c) => [
                    'id' => (int) $c->id,
                    'nombre' => $c->nombre,
                    'total' => round((float) $c->total_cantidad, 2),
                ])->values(),
            ],
            'filas' => $filas,
            'totales' => [
                'operaciones' => (int) ($totalesOperacion->operaciones ?? 0),
                'horas_trabajadas' => round($totalHoras, 2),
                'litros' => round($totalLitros, 2),
                'costo' => round($totalCosto, 2),
                'litros_por_hora' => $totalHoras > 0 ? round($totalLitros / $totalHoras, 2) : 0,
            ],
        ];
    }

    /**
     * Detalle por vehículo + totales generales del rango. El recorrido se toma
     * del kilometraje o del horómetro según el tipo_medicion del vehículo; las
     * lecturas incompletas (algún extremo nulo) aportan 0.
     *
     * @param  array<int, int>  $idsVehiculo  Vacío = todos los vehículos; con valores = sólo esos (comparación).
     * @param  int|null  $idTipoCombustible  Campo propio de vehiculo (1 vehículo = 1 tipo de combustible).
     * @param  int|null  $idArea  Asignación vigente en vehiculo_area (ACTIVO y fecha_culminacion nula o futura).
     * @return array{vehiculos: Collection, totales: array<string, float|int>}
     */
    private function obtenerResumen($fechaInicio, $fechaFin, array $idsVehiculo = [], ?int $idTipoCombustible = null, ?int $idArea = null): array
    {
        $desde = Carbon::parse($fechaInicio)->startOfDay();
        $hasta = Carbon::parse($fechaFin)->endOfDay();

        $recorridoKm = 'CASE WHEN od.kilometraje_inicio IS NOT NULL AND od.kilometraje_fin IS NOT NULL
                             THEN od.kilometraje_fin - od.kilometraje_inicio ELSE 0 END';
        $recorridoHr = 'CASE WHEN od.horometro_inicio IS NOT NULL AND od.horometro_fin IS NOT NULL
                             THEN od.horometro_fin - od.horometro_inicio ELSE 0 END';

        // Base reutilizada por las dos consultas (filas por vehículo + totales),
        // así el filtro se escribe una sola vez.
        $base = fn () => DB::table('operacion_diaria as od')
            ->join('vehiculo as v', 'v.id', '=', 'od.id_vehiculo')
            ->join('tipo_combustible as tc', 'tc.id', '=', 'v.id_tipo_combustible')
            ->whereBetween('od.fecha_inicio', [$desde, $hasta])
            ->when(! empty($idsVehiculo), fn ($q) => $q->whereIn('od.id_vehiculo', $idsVehiculo))
            ->when($idTipoCombustible, fn ($q) => $q->where('v.id_tipo_combustible', $idTipoCombustible))
            ->when($idArea, fn ($q) => $q->whereExists(function ($sub) use ($idArea) {
                $sub->select(DB::raw(1))
                    ->from('vehiculo_area')
                    ->whereColumn('vehiculo_area.id_vehiculo', 'v.id')
                    ->where('vehiculo_area.id_area', $idArea)
                    ->where('vehiculo_area.estado_asignacion', 'ACTIVO')
                    ->where(function ($q2) {
                        $q2->whereNull('vehiculo_area.fecha_culminacion')
                            ->orWhere('vehiculo_area.fecha_culminacion', '>', now());
                    });
            }));

        // Una fila por vehículo: tipo_medicion y tipo_combustible son 1:1 con el
        // vehículo, así que entran al GROUP BY sin fragmentar el grupo.
        $vehiculos = $base()
            ->groupBy('v.id', 'v.codigo', 'v.nro_placa', 'v.marca', 'v.tipo_medicion', 'tc.tipo_combustible')
            ->orderByRaw('SUM(od.horas_trabajadas) DESC')
            ->selectRaw("
                v.id as id_vehiculo,
                v.codigo,
                v.nro_placa,
                v.marca,
                v.tipo_medicion,
                tc.tipo_combustible,
                COUNT(od.id) as total_operaciones,
                COUNT(DISTINCT DATE(od.fecha_inicio)) as dias_operados,
                MAX(od.fecha_inicio) as ultima_operacion,
                ROUND(COALESCE(SUM(od.horas_trabajadas), 0), 2) as total_horas,
                ROUND(COALESCE(AVG(od.horas_trabajadas), 0), 2) as promedio_horas,
                SUM(CASE WHEN od.turno = 'DIA' THEN 1 ELSE 0 END) as operaciones_dia,
                SUM(CASE WHEN od.turno = 'NOCHE' THEN 1 ELSE 0 END) as operaciones_noche,
                ROUND(CASE WHEN v.tipo_medicion = 'kilometraje'
                           THEN COALESCE(SUM($recorridoKm), 0)
                           ELSE COALESCE(SUM($recorridoHr), 0) END, 2) as total_recorrido,
                CASE WHEN v.tipo_medicion = 'kilometraje' THEN 'km' ELSE 'h' END as unidad_recorrido
            ")
            ->get()
            ->map(fn ($r) => [
                'id_vehiculo' => (int) $r->id_vehiculo,
                'codigo' => $r->codigo,
                'nro_placa' => $r->nro_placa,
                'marca' => $r->marca,
                'tipo_medicion' => $r->tipo_medicion,
                'tipo_combustible' => $r->tipo_combustible,
                'total_operaciones' => (int) $r->total_operaciones,
                'dias_operados' => (int) $r->dias_operados,
                'ultima_operacion' => $r->ultima_operacion,
                'total_horas' => round((float) $r->total_horas, 2),
                'promedio_horas' => round((float) $r->promedio_horas, 2),
                'operaciones_dia' => (int) $r->operaciones_dia,
                'operaciones_noche' => (int) $r->operaciones_noche,
                'total_recorrido' => round((float) $r->total_recorrido, 2),
                'unidad_recorrido' => $r->unidad_recorrido,
            ]);

        // Totales generales en la BD (sin acumular en PHP): km y horas de
        // horómetro se totalizan por separado porque no son la misma unidad.
        $totales = $base()
            ->selectRaw("
                COUNT(od.id) as total_operaciones,
                COUNT(DISTINCT od.id_vehiculo) as total_vehiculos,
                ROUND(COALESCE(SUM(od.horas_trabajadas), 0), 2) as total_horas,
                ROUND(COALESCE(SUM($recorridoKm), 0), 2) as total_km,
                ROUND(COALESCE(SUM($recorridoHr), 0), 2) as total_horometro,
                SUM(CASE WHEN od.turno = 'DIA' THEN 1 ELSE 0 END) as operaciones_dia,
                SUM(CASE WHEN od.turno = 'NOCHE' THEN 1 ELSE 0 END) as operaciones_noche
            ")
            ->first();

        return [
            'vehiculos' => $vehiculos,
            'totales' => [
                'total_operaciones' => (int) ($totales->total_operaciones ?? 0),
                'total_vehiculos' => (int) ($totales->total_vehiculos ?? 0),
                'total_horas' => round((float) ($totales->total_horas ?? 0), 2),
                'total_km' => round((float) ($totales->total_km ?? 0), 2),
                'total_horometro' => round((float) ($totales->total_horometro ?? 0), 2),
                'operaciones_dia' => (int) ($totales->operaciones_dia ?? 0),
                'operaciones_noche' => (int) ($totales->operaciones_noche ?? 0),
            ],
        ];
    }

    /**
     * Ids de vehículo del filtro de comparación, normalizados a enteros
     * positivos únicos. Acepta tanto id_vehiculo[]=1&id_vehiculo[]=2 como un
     * único id_vehiculo=1 (el cast a array cubre ambos casos).
     *
     * @return array<int, int>
     */
    private function idsVehiculo(Request $request): array
    {
        return collect((array) $request->input('id_vehiculo', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Etiqueta legible de los vehículos filtrados para el encabezado del PDF:
     * hasta 3 códigos separados por coma; más de eso, sólo la cantidad.
     *
     * @param  array<int, int>  $idsVehiculo
     */
    private function etiquetaVehiculos(array $idsVehiculo): ?string
    {
        if (empty($idsVehiculo)) {
            return null;
        }

        if (count($idsVehiculo) > 3) {
            return count($idsVehiculo).' vehículos seleccionados';
        }

        $codigos = Vehiculo::whereIn('id', $idsVehiculo)->orderBy('codigo')->pluck('codigo');

        return $codigos->isNotEmpty() ? $codigos->implode(', ') : null;
    }

    /**
     * Vehículos para el select de filtro, acotados por tipo de combustible
     * (campo propio de vehiculo, 1:1) y/o área (asignación vigente en
     * vehiculo_area: estado ACTIVO y fecha_culminacion nula o futura).
     */
    private function vehiculosParaFiltro(?int $idTipoCombustible = null, ?int $idArea = null)
    {
        return Vehiculo::select('id', 'nro_placa', 'marca', 'codigo', 'tipo_medicion', 'id_tipo_combustible')
            ->with('tipoCombustible:id,tipo_combustible')
            ->when($idTipoCombustible, fn ($q) => $q->where('id_tipo_combustible', $idTipoCombustible))
            ->when($idArea, fn ($q) => $q->whereExists(function ($sub) use ($idArea) {
                $sub->select(DB::raw(1))
                    ->from('vehiculo_area')
                    ->whereColumn('vehiculo_area.id_vehiculo', 'vehiculo.id')
                    ->where('vehiculo_area.id_area', $idArea)
                    ->where('vehiculo_area.estado_asignacion', 'ACTIVO')
                    ->where(function ($q2) {
                        $q2->whereNull('vehiculo_area.fecha_culminacion')
                            ->orWhere('vehiculo_area.fecha_culminacion', '>', now());
                    });
            }))
            ->orderBy('nro_placa')
            ->get();
    }

    private function tiposCombustibleActivos()
    {
        return TipoCombustible::select('id', 'tipo_combustible')
            ->where('estado_tipo_combustible', 'ACTIVO')
            ->orderBy('tipo_combustible')
            ->get();
    }

    private function areasActivas()
    {
        return Area::select('id', 'nombre_area')
            ->where('estado_area', 'ACTIVO')
            ->orderBy('nombre_area')
            ->get();
    }
}
