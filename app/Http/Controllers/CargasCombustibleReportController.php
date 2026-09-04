<?php

namespace App\Http\Controllers;

use App\Libraries\Reportes;
use App\Models\Area;
use App\Models\CargaCombustible;
use App\Models\TipoCombustible;
use App\Models\TipoVehiculo;
use App\Models\Vehiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

class CargasCombustibleReportController extends Controller
{
    /**
     * Mostrar la vista de reportes de cargas de combustible. Admite los
     * mismos filtros de tipo de combustible, área y tipo de vehículo que el
     * reporte de rendimiento (ver vehiculosParaFiltro()).
     */
    public function index(Request $request): Response
    {
        $idTipoCombustible = $request->integer('id_tipo_combustible') ?: null;
        $idArea = $request->integer('id_area') ?: null;
        $idTipoVehiculo = $request->integer('id_tipo_vehiculo') ?: null;

        $vehiculos = $this->vehiculosParaFiltro($idTipoCombustible, $idArea, $idTipoVehiculo);

        // Obtener filtros del request
        $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->format('Y-m-d'));
        $fechaFin = $request->input('fecha_fin', now()->format('Y-m-d'));
        $idVehiculo = $request->input('id_vehiculo', null);

        // Obtener datos para mostrar en la vista
        $datosResumen = $this->obtenerResumen($fechaInicio, $fechaFin, $idVehiculo, $idTipoCombustible, $idArea, $idTipoVehiculo);

        return inertia('Reportes/CargasCombustibleReporte', [
            'vehiculos' => $vehiculos,
            'tiposCombustible' => $this->tiposCombustibleActivos(),
            'tiposVehiculo' => $this->tiposVehiculoActivos(),
            'areas' => $this->areasActivas(),
            'datosResumen' => $datosResumen,
            'filtros' => [
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'id_vehiculo' => $idVehiculo,
                'id_tipo_combustible' => $idTipoCombustible,
                'id_area' => $idArea,
                'id_tipo_vehiculo' => $idTipoVehiculo,
            ],
        ]);
    }

    /**
     * Generar el PDF del reporte.
     */
    public function generarPDF(Request $request)
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date',
        ]);

        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');
        $idVehiculo = $request->input('id_vehiculo', null);
        $idTipoVehiculo = $request->integer('id_tipo_vehiculo') ?: null;

        // Se resuelve a texto legible aquí (no en Reportes.php) para que el
        // PDF deje constancia de qué filtro se aplicó, igual que se ve en
        // pantalla (mismo criterio que generarPDFRendimiento()).
        $tipoVehiculoLabel = $idTipoVehiculo ? TipoVehiculo::find($idTipoVehiculo)?->tipo_vehiculo : null;

        $reporte = new Reportes;
        $reporte->generarReporteCargasCombustible($fechaInicio, $fechaFin, $idVehiculo, $idTipoVehiculo, $tipoVehiculoLabel);
    }

    /**
     * Generar el PDF del reporte general de rendimiento (uno o varios
     * vehículos comparados, sin gráfico). Admite los mismos filtros de tipo
     * de combustible y área que la vista (ver generarReporteRendimiento()).
     */
    public function generarPDFRendimiento(Request $request)
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date',
        ]);

        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');
        $idsVehiculo = array_filter((array) $request->input('id_vehiculo', []));
        $idTipoCombustible = $request->integer('id_tipo_combustible') ?: null;
        $idArea = $request->integer('id_area') ?: null;

        $resultado = $this->obtenerResumenRendimiento($fechaInicio, $fechaFin, $idsVehiculo, true, $idTipoCombustible, $idArea);

        // Se resuelven a texto legible aquí (no en Reportes.php) para que el
        // PDF deje constancia de qué filtro se aplicó, igual que se ve en pantalla.
        $filtrosAplicados = [
            'tipo_combustible' => $idTipoCombustible ? TipoCombustible::find($idTipoCombustible)?->tipo_combustible : null,
            'area' => $idArea ? Area::find($idArea)?->nombre_area : null,
        ];

        $reporte = new Reportes;
        $contenido = $reporte->generarReporteRendimiento($resultado, $fechaInicio, $fechaFin, 'S', null, $filtrosAplicados);

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="reporte_rendimiento_combustible.pdf"',
        ]);
    }

    /**
     * Generar el PDF del detalle de rendimiento de UN solo vehículo.
     * Igual que detalleRendimientoVehiculo(): siempre delega en
     * obtenerResumenRendimiento() con $soloResumen = false y un único id.
     */
    public function generarPDFDetalleRendimiento(Request $request)
    {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date',
            'id_vehiculo' => 'required|integer|exists:vehiculo,id',
        ]);

        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');
        $idVehiculo = $request->integer('id_vehiculo');

        $vehiculo = Vehiculo::select('id', 'nro_placa', 'marca', 'codigo', 'tipo_medicion', 'id_tipo_combustible')
            ->with('tipoCombustible:id,tipo_combustible')
            ->findOrFail($idVehiculo);
        $detalle = $this->obtenerResumenRendimiento($fechaInicio, $fechaFin, [$idVehiculo], false);

        $reporte = new Reportes;
        $contenido = $reporte->generarReporteDetalleRendimiento($vehiculo, $detalle, $fechaInicio, $fechaFin, 'S');

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="detalle_rendimiento_'.$vehiculo->codigo.'.pdf"',
        ]);
    }

    /**
     * @param  int|null  $idTipoCombustible  Campo propio de vehiculo (1 vehículo = 1 tipo de combustible).
     * @param  int|null  $idArea  Asignación vigente en vehiculo_area (estado ACTIVO y fecha_culminacion nula o futura).
     * @param  int|null  $idTipoVehiculo  Campo propio de vehiculo (1 vehículo = 1 tipo de vehículo).
     */
    private function obtenerResumen($fechaInicio, $fechaFin, $idVehiculo = null, ?int $idTipoCombustible = null, ?int $idArea = null, ?int $idTipoVehiculo = null)
    {
        $query = CargaCombustible::join(
            'vehiculo as v',
            'v.id',
            '=',
            'carga_combustible.id_vehiculo'
        )->join('tipo_combustible as tc', 'tc.id', '=', 'v.id_tipo_combustible')
            ->whereBetween('fecha_carga', [$fechaInicio, $fechaFin])
            ->when($idVehiculo, function ($query) use ($idVehiculo) {
                return $query->where('carga_combustible.id_vehiculo', $idVehiculo);
            })
            ->when($idTipoCombustible, fn ($q) => $q->where('v.id_tipo_combustible', $idTipoCombustible))
            ->when($idTipoVehiculo, fn ($q) => $q->where('v.id_tipo_vehiculo', $idTipoVehiculo))
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
            }))
            ->select([
                'carga_combustible.id_vehiculo',
                'v.nro_placa',
                'v.marca',
                'v.codigo',
                'tc.tipo_combustible',
                DB::raw('SUM(carga_combustible.litros) as total_litros'),
                DB::raw('SUM(carga_combustible.litros * carga_combustible.precio) as total_costo'),
                DB::raw('COUNT(carga_combustible.id) as cantidad_cargas'),
                DB::raw('ROUND(AVG(carga_combustible.precio), 2) as precio_promedio'),
            ])->groupBy('carga_combustible.id_vehiculo', 'v.nro_placa', 'v.marca', 'v.codigo', 'tc.tipo_combustible');

        $cargas = $query->get();

        // Totales generales
        $totalLitros = $cargas->sum('total_litros');
        $totalCosto = $cargas->sum('total_costo');

        // Agrupar por vehículo
        $vehiculosDetalle = $cargas->groupBy('id_vehiculo')->map(function ($grupo) {
            $primerCarga = $grupo->first();

            return [
                'id_vehiculo' => $primerCarga->id_vehiculo,
                'nro_placa' => $primerCarga->nro_placa,
                'marca' => $primerCarga->marca,
                'codigo' => $primerCarga->codigo,
                'tipo_combustible' => $primerCarga->tipo_combustible,
                'total_litros' => $grupo->sum('total_litros'),
                'total_costo' => $grupo->sum('total_costo'),
                'cantidad_cargas' => $grupo->count(),
                'precio_promedio' => round($grupo->avg('precio_promedio'), 2),
            ];
        })->values();

        return [
            'total_litros' => round($totalLitros, 2),
            'total_costo' => round($totalCosto, 2),
            'cantidad_cargas' => $cargas->count(),
            'vehiculos' => $vehiculosDetalle,
        ];
    }

    public function generarReporteRendimiento(Request $request)
    {
        $idTipoCombustible = $request->integer('id_tipo_combustible') ?: null;
        $idArea = $request->integer('id_area') ?: null;

        $vehiculos = $this->vehiculosParaFiltro($idTipoCombustible, $idArea);

        $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->format('Y-m-d 00:00:00'));
        $fechaFin = $request->input('fecha_fin', now()->endOfMonth()->format('Y-m-d 23:59:59'));
        // Selección múltiple: llega como id_vehiculo[]=1&id_vehiculo[]=2 (o vacío = todos los vehículos).
        $idsVehiculo = array_filter((array) $request->input('id_vehiculo', []));

        $resultado = $this->obtenerResumenRendimiento($fechaInicio, $fechaFin, $idsVehiculo, true, $idTipoCombustible, $idArea);

        return inertia('Reportes/CargasCombustibleRendimientoReporte', [
            'vehiculos' => $vehiculos,
            'tiposCombustible' => $this->tiposCombustibleActivos(),
            'areas' => $this->areasActivas(),
            'resultado' => $resultado,
            'filtros' => [
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'id_vehiculo' => array_values($idsVehiculo),
                'id_tipo_combustible' => $idTipoCombustible,
                'id_area' => $idArea,
            ],
        ]);
    }

    /**
     * Detalle carga por carga del rendimiento de UN solo vehículo (drill-down
     * del resumen de generarReporteRendimiento). Siempre delega en
     * obtenerResumenRendimiento() con $soloResumen = false y un único id,
     * nunca con la lista vacía/todos los vehículos.
     *
     * A diferencia del resumen, aquí NO se filtra por tipo de combustible ni
     * área: al ver el detalle ya se eligió un único vehículo específico, así
     * que acotar el select no tendría sentido. El tipo de combustible se
     * muestra igual como etiqueta informativa (ver 'tipoCombustible' abajo).
     */
    public function detalleRendimientoVehiculo(Request $request): Response
    {
        $vehiculos = $this->vehiculosParaFiltro();

        $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->format('Y-m-d 00:00:00'));
        $fechaFin = $request->input('fecha_fin', now()->endOfMonth()->format('Y-m-d 23:59:59'));
        $idVehiculo = $request->integer('id_vehiculo') ?: null;

        // Sin vehículo seleccionado todavía no hay nada que consultar: se
        // muestra el estado vacío en la vista en vez de forzar un error.
        $detalle = $idVehiculo
            ? $this->obtenerResumenRendimiento($fechaInicio, $fechaFin, [$idVehiculo], false)
            : collect();

        return inertia('Reportes/CargasCombustibleRendimientoDetalle', [
            'vehiculos' => $vehiculos,
            'detalle' => $detalle,
            'filtros' => [
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'id_vehiculo' => $idVehiculo,
            ],
        ]);
    }

    /**
     * Vehículos disponibles para los selects de filtro. Sólo el resumen
     * (generarReporteRendimiento) acota por tipo de combustible (campo propio
     * de vehiculo, 1:1), tipo de vehículo (campo propio de vehiculo, 1:1) y/o
     * área (asignación vigente en vehiculo_area: estado ACTIVO y
     * fecha_culminacion nula o futura); el detalle de un solo vehículo llama
     * a esto sin filtros. Siempre incluye el tipo de combustible como
     * relación (para mostrarlo como etiqueta en la UI).
     */
    private function vehiculosParaFiltro(?int $idTipoCombustible = null, ?int $idArea = null, ?int $idTipoVehiculo = null)
    {
        return Vehiculo::select('id', 'nro_placa', 'marca', 'codigo', 'tipo_medicion', 'id_tipo_combustible')
            ->with('tipoCombustible:id,tipo_combustible')
            ->when($idTipoCombustible, fn ($q) => $q->where('id_tipo_combustible', $idTipoCombustible))
            ->when($idTipoVehiculo, fn ($q) => $q->where('id_tipo_vehiculo', $idTipoVehiculo))
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

    private function tiposVehiculoActivos()
    {
        return TipoVehiculo::select('id', 'tipo_vehiculo')
            ->where('estado_tipo_vehiculo', 'ACTIVO')
            ->orderBy('tipo_vehiculo')
            ->get();
    }

    private function areasActivas()
    {
        return Area::select('id', 'nombre_area')
            ->where('estado_area', 'ACTIVO')
            ->orderBy('nombre_area')
            ->get();
    }

    /**
     * @param  array<int, int|string>  $idsVehiculo  Vacío = todos los vehículos.
     * @param  int|null  $idTipoCombustible  Campo propio de vehiculo (1 vehículo = 1 tipo de combustible).
     * @param  int|null  $idArea  Asignación vigente en vehiculo_area (estado ACTIVO y fecha_culminacion nula o futura).
     */
    public function obtenerResumenRendimiento($fechaInicio, $fechaFin, array $idsVehiculo = [], $soloResumen = true, ?int $idTipoCombustible = null, ?int $idArea = null)
    {
        // 1. CTE Base: Obtiene mediciones y el valor anterior mediante LAG()
        $cteMediciones = DB::table('carga_combustible as cc')
            ->join('vehiculo as v', 'v.id', '=', 'cc.id_vehiculo')
            ->join('tipo_combustible as tc', 'tc.id', '=', 'v.id_tipo_combustible')
            ->selectRaw("
            v.id as id_vehiculo,
            v.codigo,
            v.nro_placa,
            v.tipo_medicion,
            tc.tipo_combustible,
            cc.id as id_carga,
            cc.fecha_carga,
            cc.litros,
            CASE WHEN v.tipo_medicion = 'kilometraje' THEN cc.kilometraje ELSE cc.horometro END as medicion_actual,
            LAG(CASE WHEN v.tipo_medicion = 'kilometraje' THEN cc.kilometraje ELSE cc.horometro END)
                OVER (PARTITION BY cc.id_vehiculo ORDER BY cc.fecha_carga, cc.id) as medicion_anterior
        ")
            ->when(! empty($idsVehiculo), fn ($q) => $q->whereIn('cc.id_vehiculo', $idsVehiculo))
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

        // 2. CTE Detalle: Filtra por fechas y calcula el recorrido/rendimiento por cada carga
        $cteDetalle = DB::table('mediciones_base')
            ->selectRaw("
            id_vehiculo, codigo, nro_placa, tipo_medicion, tipo_combustible, id_carga, fecha_carga, litros,
            medicion_anterior, medicion_actual,
            (medicion_actual - medicion_anterior) as recorrido,
            CASE
                WHEN tipo_medicion = 'kilometraje' THEN
                    CASE WHEN litros > 0 THEN ROUND((medicion_actual - medicion_anterior) / litros, 2) ELSE 0 END
                WHEN tipo_medicion = 'horometro' THEN
                    CASE WHEN (medicion_actual - medicion_anterior) > 0 THEN ROUND(litros / (medicion_actual - medicion_anterior), 2) ELSE 0 END
                ELSE 0
            END as rendimiento
        ")
            ->whereNotNull('medicion_anterior')
            ->whereBetween('fecha_carga', [$fechaInicio, $fechaFin]);

        // Opción A: Retornar solo el Resumen Agrupado por Vehículo
        if ($soloResumen) {
            $resultado = DB::table('detalle_mediciones')
                ->withExpression('mediciones_base', $cteMediciones)
                ->withExpression('detalle_mediciones', $cteDetalle)
                ->selectRaw("
                id_vehiculo,
                codigo,
                nro_placa,
                tipo_medicion,
                tipo_combustible,
                COUNT(id_carga) as total_cargas,
                ROUND(SUM(litros), 2) as total_litros,
                ROUND(SUM(recorrido), 2) as total_recorrido,
                CASE
                    WHEN tipo_medicion = 'kilometraje' THEN
                        CASE WHEN SUM(litros) > 0 THEN ROUND(SUM(recorrido) / SUM(litros), 2) ELSE 0 END
                    WHEN tipo_medicion = 'horometro' THEN
                        CASE WHEN SUM(recorrido) > 0 THEN ROUND(SUM(litros) / SUM(recorrido), 2) ELSE 0 END
                    ELSE 0
                END as rendimiento_promedio,
                CASE
                    WHEN tipo_medicion = 'kilometraje' THEN 'km/L'
                    WHEN tipo_medicion = 'horometro' THEN 'L/h'
                    ELSE ''
                END as unidad_medida
            ")
                ->groupBy('id_vehiculo', 'codigo', 'nro_placa', 'tipo_medicion', 'tipo_combustible')
                ->get();

            return $resultado;
        }

        // Opción B: Retornar el Detalle completo
        return DB::table('detalle_mediciones')
            ->withExpression('mediciones_base', $cteMediciones)
            ->withExpression('detalle_mediciones', $cteDetalle)
            ->get();
    }
}
