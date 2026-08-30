<?php

namespace App\Http\Controllers;

use App\Libraries\Reportes;
use App\Models\ParametrosEmpresa;
use App\Models\VehiculoExterno;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

class ControlCargasReportController extends Controller
{
    /**
     * Ámbitos válidos para el filtro "al exterior": 'todos' (sin filtro),
     * 'exterior' (sólo fletes marcados es_al_exterior) y 'nacional' (sólo los
     * que no lo están).
     */
    private const AMBITOS = ['todos', 'exterior', 'nacional'];

    /**
     * Reporte de control de carga de material: cantidad de viajes realizados
     * por cada vehículo externo, con filtros de rango de fechas, vehículo
     * externo y ámbito (todos / al exterior / nacional).
     */
    public function index(Request $request): Response
    {
        $filtros = $this->filtrosDesde($request);

        return inertia('Reportes/ControlCargasReporte', [
            'vehiculosExternos' => $this->vehiculosExternosParaFiltro(),
            'datosResumen' => $this->obtenerResumen($filtros),
            'filtros' => $filtros,
        ]);
    }

    /**
     * Genera el PDF del reporte con los mismos filtros que la vista.
     */
    public function generarPDF(Request $request)
    {
        $request->validate([
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date',
        ]);

        $filtros = $this->filtrosDesde($request);
        $resumen = $this->obtenerResumen($filtros);

        $reporte = new Reportes;
        $contenido = $reporte->generarReporteControlCargas(
            $resumen,
            $filtros['fecha_desde'],
            $filtros['fecha_hasta'],
            $filtros['ambito'],
            'S',
        );

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="reporte_control_cargas_material.pdf"',
        ]);
    }

    /**
     * Normaliza los filtros del request. El rango de fechas por defecto es
     * "Este mes" (mismo criterio que DateRangeFilter.vue y el resto de
     * reportes): así la primera carga ya viene filtrada y el componente de
     * rango no dispara una petición extra al montarse.
     *
     * @return array{fecha_desde: ?string, fecha_hasta: ?string, id_vehiculo_externo: ?int, ambito: string}
     */
    private function filtrosDesde(Request $request): array
    {
        $ambito = $request->input('ambito', 'todos');

        return [
            'fecha_desde' => $request->input('fecha_desde', now()->startOfMonth()->format('Y-m-d')),
            'fecha_hasta' => $request->input('fecha_hasta', now()->format('Y-m-d')),
            'id_vehiculo_externo' => $request->integer('id_vehiculo_externo') ?: null,
            'ambito' => in_array($ambito, self::AMBITOS, true) ? $ambito : 'todos',
        ];
    }

    /**
     * Resumen del reporte: una fila por vehículo externo (viajes, fletes,
     * reparto exterior/nacional y monto pagado) + una fila de totales
     * generales. Todo el agregado se resuelve en la base de datos; en PHP sólo
     * se castean/redondean los valores escalares que ya devuelve el motor.
     *
     * El rango de fechas filtra por la fecha de apertura del flete
     * (carga_material.fecha_apertura). Los viajes se cuentan con un
     * leftJoinSub ya agregado por flete: así el join a carga_material queda
     * 1:1 y SUM(monto_pago) no se multiplica por la cantidad de viajes
     * ("fan-out"). El leftJoin incluye también los fletes abiertos en el
     * rango que todavía no tienen viajes (cuentan como flete, 0 viajes).
     *
     * @param  array{fecha_desde: ?string, fecha_hasta: ?string, id_vehiculo_externo: ?int, ambito: string}  $filtros
     * @return array{vehiculos: Collection<int, array<string, mixed>>, totales: array<string, int|float>}
     */
    private function obtenerResumen(array $filtros): array
    {
        // Subconsulta: una fila por flete con su cantidad total de viajes.
        $viajesPorFlete = DB::table('viaje')
            ->select('id_carga_material', DB::raw('COUNT(*) as viajes'))
            ->groupBy('id_carga_material');

        // Base común de los dos agregados (detalle por vehículo y totales):
        // fletes filtrados por fecha de apertura, ámbito y vehículo.
        $base = fn () => DB::table('carga_material as cm')
            ->leftJoinSub($viajesPorFlete, 'vf', 'vf.id_carga_material', '=', 'cm.id')
            ->when($filtros['fecha_desde'], fn ($q) => $q->whereDate('cm.fecha_apertura', '>=', $filtros['fecha_desde']))
            ->when($filtros['fecha_hasta'], fn ($q) => $q->whereDate('cm.fecha_apertura', '<=', $filtros['fecha_hasta']))
            ->when($filtros['id_vehiculo_externo'], fn ($q) => $q->where('cm.id_vehiculo_externo', $filtros['id_vehiculo_externo']))
            ->when($filtros['ambito'] === 'exterior', fn ($q) => $q->where('cm.es_al_exterior', true))
            ->when($filtros['ambito'] === 'nacional', fn ($q) => $q->where('cm.es_al_exterior', false));

        $filas = $base()
            ->join('vehiculo_externo as ve', 've.id', '=', 'cm.id_vehiculo_externo')
            ->groupBy('ve.id', 've.nro_placa', 've.propietario')
            ->select([
                've.id as id_vehiculo_externo',
                've.nro_placa',
                've.propietario',
                DB::raw('COALESCE(SUM(vf.viajes), 0) as total_viajes'),
                DB::raw('COUNT(cm.id) as total_fletes'),
                DB::raw('COALESCE(SUM(CASE WHEN cm.es_al_exterior = 1 THEN vf.viajes ELSE 0 END), 0) as viajes_exterior'),
                DB::raw('COALESCE(SUM(CASE WHEN cm.es_al_exterior = 0 THEN vf.viajes ELSE 0 END), 0) as viajes_nacional'),
                DB::raw('SUM(cm.monto_pago) as monto_total'),
            ])
            ->orderByDesc('total_viajes')
            ->orderBy('ve.nro_placa')
            ->get();

        $totales = $base()
            ->selectRaw('COUNT(DISTINCT cm.id_vehiculo_externo) as total_vehiculos')
            ->selectRaw('COUNT(cm.id) as total_fletes')
            ->selectRaw('COALESCE(SUM(vf.viajes), 0) as total_viajes')
            ->selectRaw('COALESCE(SUM(CASE WHEN cm.es_al_exterior = 1 THEN vf.viajes ELSE 0 END), 0) as viajes_exterior')
            ->selectRaw('COALESCE(SUM(CASE WHEN cm.es_al_exterior = 0 THEN vf.viajes ELSE 0 END), 0) as viajes_nacional')
            ->selectRaw('SUM(cm.monto_pago) as monto_total')
            ->first();

        return [
            'vehiculos' => $filas->map(fn ($fila) => [
                'id_vehiculo_externo' => (int) $fila->id_vehiculo_externo,
                'nro_placa' => $fila->nro_placa,
                'propietario' => $fila->propietario,
                'total_viajes' => (int) $fila->total_viajes,
                'total_fletes' => (int) $fila->total_fletes,
                'viajes_exterior' => (int) $fila->viajes_exterior,
                'viajes_nacional' => (int) $fila->viajes_nacional,
                'monto_total' => round((float) $fila->monto_total, 2),
            ])->values(),
            'totales' => [
                'total_vehiculos' => (int) ($totales->total_vehiculos ?? 0),
                'total_fletes' => (int) ($totales->total_fletes ?? 0),
                'total_viajes' => (int) ($totales->total_viajes ?? 0),
                'viajes_exterior' => (int) ($totales->viajes_exterior ?? 0),
                'viajes_nacional' => (int) ($totales->viajes_nacional ?? 0),
                'monto_total' => round((float) ($totales->monto_total ?? 0), 2),
            ],
        ];
    }

    /**
     * Detalle de un solo vehículo externo (drill-down del resumen): todos sus
     * fletes abiertos en el rango, el desglose de viajes por material (colá,
     * broza, concentrado, …) de cada flete y el total por material del
     * vehículo (para el gráfico de pastel). Filtra por fecha de apertura del
     * flete (carga_material.fecha_apertura) — no por ámbito.
     */
    public function detalle(Request $request): Response
    {
        $filtros = $this->filtrosDetalle($request);

        $vehiculo = $filtros['id_vehiculo_externo']
            ? VehiculoExterno::find($filtros['id_vehiculo_externo'])
            : null;

        return inertia('Reportes/ControlCargasReporteDetalle', [
            'vehiculosExternos' => $this->vehiculosExternosParaFiltro(),
            'vehiculo' => $vehiculo,
            // Sin vehículo elegido todavía no hay nada que consultar: la vista
            // muestra el estado vacío en vez de forzar un error.
            'detalle' => $vehiculo ? $this->obtenerDetalleVehiculo($vehiculo, $filtros) : null,
            'filtros' => $filtros,
        ]);
    }

    /**
     * PDF del detalle de un vehículo externo (mismos filtros que la vista).
     */
    public function generarPDFDetalle(Request $request)
    {
        $request->validate([
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date',
            'id_vehiculo_externo' => 'required|integer|exists:vehiculo_externo,id',
        ]);

        $filtros = $this->filtrosDetalle($request);
        $vehiculo = VehiculoExterno::findOrFail($filtros['id_vehiculo_externo']);

        $reporte = new Reportes;
        $contenido = $reporte->generarReporteControlCargasDetalle(
            $vehiculo,
            $this->obtenerDetalleVehiculo($vehiculo, $filtros),
            $filtros['fecha_desde'],
            $filtros['fecha_hasta'],
            'S',
        );

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="detalle_control_cargas_'.($vehiculo->nro_placa ?: $vehiculo->id).'.pdf"',
        ]);
    }

    /**
     * @return array{fecha_desde: ?string, fecha_hasta: ?string, id_vehiculo_externo: ?int}
     */
    private function filtrosDetalle(Request $request): array
    {
        return [
            'fecha_desde' => $request->input('fecha_desde', now()->startOfMonth()->format('Y-m-d')),
            'fecha_hasta' => $request->input('fecha_hasta', now()->format('Y-m-d')),
            'id_vehiculo_externo' => $request->integer('id_vehiculo_externo') ?: null,
        ];
    }

    /**
     * @param  array{fecha_desde: ?string, fecha_hasta: ?string, id_vehiculo_externo: ?int}  $filtros
     * @return array{fletes: Collection<int, array<string, mixed>>, materiales: Collection<int, array<string, mixed>>, totales: array<string, int|float>}
     */
    private function obtenerDetalleVehiculo(VehiculoExterno $vehiculo, array $filtros): array
    {
        // Fletes del vehículo abiertos en el rango + su cantidad de viajes
        // (agregado en la BD). leftJoin: un flete abierto sin viajes todavía
        // igual aparece, con 0 viajes.
        $fletes = DB::table('carga_material as cm')
            ->leftJoin('viaje as vi', 'vi.id_carga_material', '=', 'cm.id')
            ->where('cm.id_vehiculo_externo', $vehiculo->id);
        $this->scopeFechaApertura($fletes, $filtros);
        $fletes = $fletes
            ->groupBy('cm.id', 'cm.nro_carga', 'cm.gestion', 'cm.fecha_apertura', 'cm.fecha_cierre', 'cm.estado_carga', 'cm.es_al_exterior', 'cm.pais', 'cm.monto_pago', 'cm.nombre_conductor')
            ->select('cm.id', 'cm.nro_carga', 'cm.gestion', 'cm.fecha_apertura', 'cm.fecha_cierre', 'cm.estado_carga', 'cm.es_al_exterior', 'cm.pais', 'cm.monto_pago', 'cm.nombre_conductor', DB::raw('COUNT(vi.id) as viajes_count'))
            ->orderByDesc('cm.fecha_apertura')
            ->get();

        // Desglose de viajes por material y flete (colá, broza, concentrado…).
        $materialesPorFlete = DB::table('viaje as vi')
            ->join('carga_material as cm', 'cm.id', '=', 'vi.id_carga_material')
            ->join('material as m', 'm.id', '=', 'vi.id_material')
            ->where('cm.id_vehiculo_externo', $vehiculo->id);
        $this->scopeFechaApertura($materialesPorFlete, $filtros);
        $materialesPorFlete = $materialesPorFlete
            ->groupBy('vi.id_carga_material', 'm.id', 'm.material')
            ->select('vi.id_carga_material', 'm.material', DB::raw('COUNT(vi.id) as viajes'))
            ->orderByDesc('viajes')
            ->get()
            ->groupBy(fn ($fila) => (int) $fila->id_carga_material);

        // Total de viajes por material del vehículo (gráfico de pastel + resumen).
        $materiales = DB::table('viaje as vi')
            ->join('carga_material as cm', 'cm.id', '=', 'vi.id_carga_material')
            ->join('material as m', 'm.id', '=', 'vi.id_material')
            ->where('cm.id_vehiculo_externo', $vehiculo->id);
        $this->scopeFechaApertura($materiales, $filtros);
        $materiales = $materiales
            ->groupBy('m.id', 'm.material')
            ->select('m.material', DB::raw('COUNT(vi.id) as viajes'))
            ->orderByDesc('viajes')
            ->get();

        $digitos = (int) (ParametrosEmpresa::first()?->parametros_vale->digitos_serie ?? 6);

        return [
            'fletes' => $fletes->map(fn ($flete) => [
                'id' => (int) $flete->id,
                'nro' => str_pad((string) $flete->nro_carga, $digitos, '0', STR_PAD_LEFT).'/'.$flete->gestion,
                'fecha_apertura' => $flete->fecha_apertura,
                'fecha_cierre' => $flete->fecha_cierre,
                'estado_carga' => $flete->estado_carga,
                'es_al_exterior' => (bool) $flete->es_al_exterior,
                'pais' => $flete->pais,
                'nombre_conductor' => $flete->nombre_conductor,
                'monto_pago' => $flete->monto_pago !== null ? round((float) $flete->monto_pago, 2) : null,
                'viajes_count' => (int) $flete->viajes_count,
                'materiales' => ($materialesPorFlete->get((int) $flete->id) ?? collect())
                    ->map(fn ($mat) => ['material' => $mat->material, 'viajes' => (int) $mat->viajes])
                    ->values(),
            ])->values(),
            'materiales' => $materiales->map(fn ($mat) => [
                'material' => $mat->material,
                'viajes' => (int) $mat->viajes,
            ])->values(),
            'totales' => [
                'total_fletes' => $fletes->count(),
                'total_viajes' => (int) $materiales->sum('viajes'),
                'total_materiales' => $materiales->count(),
                'monto_total' => round((float) $fletes->sum(fn ($flete) => (float) $flete->monto_pago), 2),
            ],
        ];
    }

    /**
     * Acota una consulta al rango de fechas de apertura del flete
     * (carga_material.fecha_apertura). La consulta debe tener la tabla
     * carga_material aliada como "cm".
     *
     * @param  array{fecha_desde: ?string, fecha_hasta: ?string}  $filtros
     */
    private function scopeFechaApertura(Builder $query, array $filtros): void
    {
        $query->when($filtros['fecha_desde'], fn ($q) => $q->whereDate('cm.fecha_apertura', '>=', $filtros['fecha_desde']))
            ->when($filtros['fecha_hasta'], fn ($q) => $q->whereDate('cm.fecha_apertura', '<=', $filtros['fecha_hasta']));
    }

    private function vehiculosExternosParaFiltro()
    {
        return VehiculoExterno::orderBy('nro_placa')->get(['id', 'nro_placa', 'propietario']);
    }
}
