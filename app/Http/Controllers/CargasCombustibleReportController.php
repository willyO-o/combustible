<?php

namespace App\Http\Controllers;

use App\Libraries\Reportes;
use App\Models\CargaCombustible;
use App\Models\Vehiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

class CargasCombustibleReportController extends Controller
{
    /**
     * Mostrar la vista de reportes de cargas de combustible.
     */
    public function index(Request $request): Response
    {
        $vehiculos = Vehiculo::select('id', 'nro_placa', 'marca', 'codigo')
            ->orderBy('nro_placa')
            ->get();

        // Obtener filtros del request
        $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->format('Y-m-d'));
        $fechaFin = $request->input('fecha_fin', now()->format('Y-m-d'));
        $idVehiculo = $request->input('id_vehiculo', null);

        // Obtener datos para mostrar en la vista
        $datosResumen = $this->obtenerResumen($fechaInicio, $fechaFin, $idVehiculo);

        return inertia('Reportes/CargasCombustibleReporte', [
            'vehiculos' => $vehiculos,
            'datosResumen' => $datosResumen,
            'filtros' => [
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'id_vehiculo' => $idVehiculo,
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

        $reporte = new Reportes;
        $reporte->generarReporteCargasCombustible($fechaInicio, $fechaFin, $idVehiculo);
    }

    private function obtenerResumen($fechaInicio, $fechaFin, $idVehiculo = null)
    {
        $query = CargaCombustible::join(
            'vehiculo as v',
            'v.id',
            '=',
            'carga_combustible.id_vehiculo'
        )->whereBetween('fecha_carga', [$fechaInicio, $fechaFin])
            ->when($idVehiculo, function ($query) use ($idVehiculo) {
                return $query->where('carga_combustible.id_vehiculo', $idVehiculo);
            })
            ->select([
                'carga_combustible.id_vehiculo',
                'v.nro_placa',
                'v.marca',
                'v.codigo',
                DB::raw('SUM(carga_combustible.litros) as total_litros'),
                DB::raw('SUM(carga_combustible.litros * carga_combustible.precio) as total_costo'),
                DB::raw('COUNT(carga_combustible.id) as cantidad_cargas'),
                DB::raw('ROUND(AVG(carga_combustible.precio), 2) as precio_promedio'),
            ])->groupBy('carga_combustible.id_vehiculo', 'v.nro_placa', 'v.marca', 'v.codigo');

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
        $vehiculos = Vehiculo::select('id', 'nro_placa', 'marca', 'codigo', 'tipo_medicion')
            ->orderBy('nro_placa')
            ->get();

        $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->format('Y-m-d 00:00:00'));
        $fechaFin = $request->input('fecha_fin', now()->endOfMonth()->format('Y-m-d 23:59:59'));
        // Selección múltiple: llega como id_vehiculo[]=1&id_vehiculo[]=2 (o vacío = todos los vehículos).
        $idsVehiculo = array_filter((array) $request->input('id_vehiculo', []));

        $resultado = $this->obtenerResumenRendimiento($fechaInicio, $fechaFin, $idsVehiculo, true);

        return inertia('Reportes/CargasCombustibleRendimientoReporte', [
            'vehiculos' => $vehiculos,
            'resultado' => $resultado,
            'filtros' => [
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'id_vehiculo' => array_values($idsVehiculo),
            ],
        ]);
    }

    /**
     * Detalle carga por carga del rendimiento de UN solo vehículo (drill-down
     * del resumen de generarReporteRendimiento). Siempre delega en
     * obtenerResumenRendimiento() con $soloResumen = false y un único id,
     * nunca con la lista vacía/todos los vehículos.
     */
    public function detalleRendimientoVehiculo(Request $request): Response
    {
        $vehiculos = Vehiculo::select('id', 'nro_placa', 'marca', 'codigo', 'tipo_medicion')
            ->orderBy('nro_placa')
            ->get();

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
     * @param  array<int, int|string>  $idsVehiculo  Vacío = todos los vehículos.
     */
    public function obtenerResumenRendimiento($fechaInicio, $fechaFin, array $idsVehiculo = [], $soloResumen = true)
    {
        // 1. CTE Base: Obtiene mediciones y el valor anterior mediante LAG()
        $cteMediciones = DB::table('carga_combustible as cc')
            ->join('vehiculo as v', 'v.id', '=', 'cc.id_vehiculo')
            ->selectRaw("
            v.id as id_vehiculo,
            v.codigo,
            v.nro_placa,
            v.tipo_medicion,
            cc.id as id_carga,
            cc.fecha_carga,
            cc.litros,
            CASE WHEN v.tipo_medicion = 'kilometraje' THEN cc.kilometraje ELSE cc.horometro END as medicion_actual,
            LAG(CASE WHEN v.tipo_medicion = 'kilometraje' THEN cc.kilometraje ELSE cc.horometro END)
                OVER (PARTITION BY cc.id_vehiculo ORDER BY cc.fecha_carga, cc.id) as medicion_anterior
        ")
            ->when(! empty($idsVehiculo), fn ($q) => $q->whereIn('cc.id_vehiculo', $idsVehiculo));

        // 2. CTE Detalle: Filtra por fechas y calcula el recorrido/rendimiento por cada carga
        $cteDetalle = DB::table('mediciones_base')
            ->selectRaw("
            id_vehiculo, codigo, nro_placa, tipo_medicion, id_carga, fecha_carga, litros,
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
                ->groupBy('id_vehiculo', 'codigo', 'nro_placa', 'tipo_medicion')
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
