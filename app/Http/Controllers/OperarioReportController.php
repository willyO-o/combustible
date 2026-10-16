<?php

namespace App\Http\Controllers;

use App\Models\Conductor;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Response;

/**
 * Reporte por operario: sus operaciones diarias (con las actividades
 * realizadas en cada jornada), el resumen de actividades y sus cargas de
 * combustible en un rango de fechas. Mismo patrón que la bitácora por
 * vehículo (OperacionDiariaReportController::detalle()): UN solo select +
 * rango de fechas, "Este mes" por defecto y sin operario elegido no se
 * consulta nada.
 *
 * Los totales se agregan en SQL; en PHP sólo se reindexan las actividades
 * por operación (lookup por clave). Nº de consultas constante.
 */
class OperarioReportController extends Controller
{
    public function index(Request $request): Response
    {
        $idConductor = $request->integer('id_conductor') ?: null;
        $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->format('Y-m-d'));
        $fechaFin = $request->input('fecha_fin', now()->format('Y-m-d'));

        $operario = $idConductor ? $this->operario($idConductor) : null;

        return inertia('Reportes/OperarioReporte', [
            'operarios' => $this->operariosParaFiltro(),
            'operario' => $operario,
            'datos' => $operario ? $this->obtenerReporteOperario($operario['id'], $fechaInicio, $fechaFin) : null,
            'filtros' => [
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'id_conductor' => $operario['id'] ?? null,
            ],
        ]);
    }

    /**
     * Todos los operarios (activos e inactivos: el reporte también sirve para
     * consultar historial), con el estado en la etiqueta.
     *
     * @return Collection<int, array{id: int, label: string}>
     */
    private function operariosParaFiltro(): Collection
    {
        return Conductor::query()
            ->join('persona', 'persona.id', '=', 'conductor.id')
            ->orderBy('persona.nombres')
            ->orderBy('persona.paterno')
            ->get(['conductor.id', 'conductor.estado_conductor', 'persona.nombres', 'persona.paterno', 'persona.materno', 'persona.ci'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'label' => trim("{$c->nombres} {$c->paterno} {$c->materno}")." (CI: {$c->ci})"
                    .($c->estado_conductor !== 'ACTIVO' ? ' — '.strtolower($c->estado_conductor) : ''),
            ])
            ->values();
    }

    /**
     * @return array{id: int, nombre_completo: string, ci: ?string, cargo: ?string, estado_conductor: string}|null
     */
    private function operario(int $idConductor): ?array
    {
        $fila = Conductor::query()
            ->join('persona', 'persona.id', '=', 'conductor.id')
            ->leftJoin('cargo', 'cargo.id', '=', 'persona.id_cargo')
            ->where('conductor.id', $idConductor)
            ->first(['conductor.id', 'conductor.estado_conductor', 'persona.nombres', 'persona.paterno', 'persona.materno', 'persona.ci', 'cargo.cargo as nombre_cargo']);

        if (! $fila) {
            return null;
        }

        return [
            'id' => $fila->id,
            'nombre_completo' => trim("{$fila->nombres} {$fila->paterno} {$fila->materno}"),
            'ci' => $fila->ci,
            'cargo' => $fila->nombre_cargo,
            'estado_conductor' => $fila->estado_conductor,
        ];
    }

    /**
     * @return array{operaciones: Collection, actividades: Collection, cargas: Collection, totales: array<string, int|float>}
     */
    private function obtenerReporteOperario(int $idConductor, string $fechaInicio, string $fechaFin): array
    {
        $desde = Carbon::parse($fechaInicio)->startOfDay();
        $hasta = Carbon::parse($fechaFin)->endOfDay();

        $operacionesBase = fn () => DB::table('operacion_diaria as od')
            ->where('od.id_conductor', $idConductor)
            ->whereBetween('od.fecha_inicio', [$desde, $hasta]);

        $operaciones = $operacionesBase()
            ->join('vehiculo as v', 'v.id', '=', 'od.id_vehiculo')
            ->orderBy('od.fecha_inicio')
            ->orderBy('od.id')
            ->get([
                'od.id', 'od.nro_operacion', 'od.fecha_inicio', 'od.fecha_fin', 'od.turno', 'od.estado',
                'od.horas_trabajadas', 'od.kilometraje_inicio', 'od.kilometraje_fin',
                'od.horometro_inicio', 'od.horometro_fin', 'od.observaciones',
                'v.codigo as vehiculo_codigo', 'v.nro_placa as vehiculo_placa', 'v.tipo_medicion',
            ]);

        // Actividades de esas jornadas, en 1 consulta, reindexadas por operación.
        $actividadesPorOperacion = $operaciones->isEmpty()
            ? collect()
            : DB::table('actividad_realizada as ar')
                ->join('actividad as a', 'a.id', '=', 'ar.id_actividad')
                ->leftJoin('material as m', 'm.id', '=', 'ar.id_material')
                ->whereIn('ar.id_operacion_diaria', $operaciones->pluck('id'))
                ->orderBy('ar.id')
                ->get([
                    'ar.id_operacion_diaria', 'a.nombre_actividad as actividad', 'ar.lugar', 'ar.origen',
                    'ar.destino', 'm.material', 'ar.cantidad', 'ar.unidad_medida',
                ])
                ->groupBy('id_operacion_diaria');

        $filasOperacion = $operaciones->map(function ($op) use ($actividadesPorOperacion) {
            $esKm = $op->tipo_medicion === 'kilometraje';

            return [
                'id' => $op->id,
                'nro_operacion' => $op->nro_operacion,
                'fecha_inicio' => $op->fecha_inicio,
                'fecha_fin' => $op->fecha_fin,
                'turno' => $op->turno,
                'estado' => $op->estado,
                'horas_trabajadas' => $op->horas_trabajadas !== null ? (float) $op->horas_trabajadas : null,
                'vehiculo' => trim("{$op->vehiculo_codigo} — ".($op->vehiculo_placa ?? 'Sin placa')),
                'tipo_medicion' => $op->tipo_medicion,
                'lectura_inicio' => $esKm ? $op->kilometraje_inicio : $op->horometro_inicio,
                'lectura_fin' => $esKm ? $op->kilometraje_fin : $op->horometro_fin,
                'observaciones' => $op->observaciones,
                'actividades' => $actividadesPorOperacion->get($op->id, collect())->map(fn ($a) => [
                    'actividad' => $a->actividad,
                    'lugar' => $a->lugar,
                    'origen' => $a->origen,
                    'destino' => $a->destino,
                    'material' => $a->material,
                    'cantidad' => $a->cantidad !== null ? (float) $a->cantidad : null,
                    'unidad_medida' => $a->unidad_medida,
                ])->values(),
            ];
        })->values();

        // Resumen por actividad (y unidad: no se suman viajes con m3).
        $resumenActividades = DB::table('actividad_realizada as ar')
            ->join('actividad as a', 'a.id', '=', 'ar.id_actividad')
            ->joinSub($operacionesBase()->select('od.id'), 'ops', 'ops.id', '=', 'ar.id_operacion_diaria')
            ->groupBy('a.id', 'a.nombre_actividad', 'ar.unidad_medida')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->orderBy('a.nombre_actividad')
            ->selectRaw('a.id, a.nombre_actividad as actividad, ar.unidad_medida, COUNT(*) as veces,
                COUNT(DISTINCT ar.id_operacion_diaria) as jornadas, SUM(ar.cantidad) as cantidad_total')
            ->get()
            ->map(fn ($r) => [
                'actividad' => $r->actividad,
                'unidad_medida' => $r->unidad_medida,
                'veces' => (int) $r->veces,
                'jornadas' => (int) $r->jornadas,
                'cantidad_total' => $r->cantidad_total !== null ? round((float) $r->cantidad_total, 2) : null,
            ])
            ->values();

        $cargas = DB::table('carga_combustible as cc')
            ->join('vehiculo as v', 'v.id', '=', 'cc.id_vehiculo')
            ->leftJoin('grifo as g', 'g.id', '=', 'cc.id_grifo')
            ->leftJoin('tipo_combustible as tc', 'tc.id', '=', 'cc.id_tipo_combustible')
            ->where('cc.id_conductor', $idConductor)
            ->whereBetween('cc.fecha_carga', [$desde, $hasta])
            ->orderBy('cc.fecha_carga')
            ->orderBy('cc.id')
            ->get([
                'cc.id', 'cc.nro_carga', 'cc.gestion', 'cc.fecha_carga', 'cc.litros', 'cc.precio',
                'cc.kilometraje', 'cc.horometro', 'cc.tipo_carga', 'cc.estado_carga',
                'v.codigo as vehiculo_codigo', 'v.nro_placa as vehiculo_placa', 'v.tipo_medicion',
                'g.razon_social as grifo', 'tc.tipo_combustible',
            ])
            ->map(fn ($c) => [
                'id' => $c->id,
                'nro' => "{$c->nro_carga}/{$c->gestion}",
                'fecha_carga' => $c->fecha_carga,
                'vehiculo' => trim("{$c->vehiculo_codigo} — ".($c->vehiculo_placa ?? 'Sin placa')),
                'tipo_combustible' => $c->tipo_combustible,
                'grifo' => $c->grifo,
                'tipo_carga' => $c->tipo_carga,
                'estado_carga' => $c->estado_carga,
                'litros' => (float) $c->litros,
                'precio' => (float) $c->precio,
                'costo' => round((float) $c->litros * (float) $c->precio, 2),
                'lectura' => $c->tipo_medicion === 'kilometraje' ? $c->kilometraje : $c->horometro,
                'tipo_medicion' => $c->tipo_medicion,
            ])
            ->values();

        $totalesOperacion = $operacionesBase()
            ->selectRaw('COUNT(*) as operaciones, COALESCE(SUM(od.horas_trabajadas), 0) as horas,
                COUNT(DISTINCT DATE(od.fecha_inicio)) as dias, COUNT(DISTINCT od.id_vehiculo) as vehiculos')
            ->first();

        // Las cargas ANULADAS se listan (marcadas) pero no suman.
        $totalesCarga = DB::table('carga_combustible')
            ->where('id_conductor', $idConductor)
            ->whereBetween('fecha_carga', [$desde, $hasta])
            ->where(fn ($q) => $q->whereNull('estado_carga')->orWhere('estado_carga', '!=', 'ANULADO'))
            ->selectRaw('COUNT(*) as cargas, COALESCE(SUM(litros), 0) as litros, COALESCE(SUM(litros * precio), 0) as costo')
            ->first();

        return [
            'operaciones' => $filasOperacion,
            'actividades' => $resumenActividades,
            'cargas' => $cargas,
            'totales' => [
                'operaciones' => (int) $totalesOperacion->operaciones,
                'horas_trabajadas' => round((float) $totalesOperacion->horas, 2),
                'dias_trabajados' => (int) $totalesOperacion->dias,
                'vehiculos' => (int) $totalesOperacion->vehiculos,
                'actividades' => (int) $resumenActividades->sum('veces'),
                'cargas' => (int) $totalesCarga->cargas,
                'litros' => round((float) $totalesCarga->litros, 2),
                'costo' => round((float) $totalesCarga->costo, 2),
            ],
        ];
    }
}
