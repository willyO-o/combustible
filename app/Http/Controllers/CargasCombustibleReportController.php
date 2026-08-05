<?php

namespace App\Http\Controllers;

use App\Libraries\Reportes;
use App\Models\CargaCombustible;
use App\Models\Vehiculo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CargasCombustibleReportController extends Controller
{
    /**
     * Mostrar la vista de reportes de cargas de combustible.
     */
    public function index(Request $request): Response
    {
        $vehiculos = Vehiculo::select('id', 'nro_placa', 'marca', 'codigo')
            ->where('estado_vehiculo', 'ACTIVO')
            ->orderBy('nro_placa')
            ->get();

        // Obtener filtros del request
        $fechaInicio = $request->get('fecha_inicio', now()->startOfMonth()->format('Y-m-d'));
        $fechaFin = $request->get('fecha_fin', now()->format('Y-m-d'));
        $idVehiculo = $request->get('id_vehiculo', null);

        // Obtener datos para mostrar en la vista
        $datosResumen = $this->obtenerResumen($fechaInicio, $fechaFin, $idVehiculo);

        return Inertia::render('Reportes/CargasCombustibleReporte', [
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

        $fechaInicio = $request->get('fecha_inicio');
        $fechaFin = $request->get('fecha_fin');
        $idVehiculo = $request->get('id_vehiculo', null);

        $reporte = new Reportes();
        $reporte->generarReporteCargasCombustible($fechaInicio, $fechaFin, $idVehiculo);
    }

    /**
     * Obtener resumen de datos filtrados.
     */
    private function obtenerResumen($fechaInicio, $fechaFin, $idVehiculo = null)
    {
        $query = CargaCombustible::whereBetween('fecha_carga', [$fechaInicio, $fechaFin])
            ->with(['vehiculo', 'tipoCombustible']);

        if ($idVehiculo) {
            $query->where('id_vehiculo', $idVehiculo);
        }

        $cargas = $query->get();

        // Totales generales
        $totalLitros = $cargas->sum('litros');
        $totalCosto = $cargas->sum(function ($carga) {
            return $carga->precio * $carga->litros;
        });

        // Agrupar por vehículo
        $vehiculosDetalle = $cargas->groupBy('id_vehiculo')->map(function ($grupo) {
            $primerCarga = $grupo->first();
            return [
                'id_vehiculo' => $primerCarga->id_vehiculo,
                'nro_placa' => $primerCarga->vehiculo->nro_placa,
                'marca' => $primerCarga->vehiculo->marca,
                'codigo' => $primerCarga->vehiculo->codigo,
                'total_litros' => $grupo->sum('litros'),
                'total_costo' => $grupo->sum(function ($c) {
                    return $c->precio * $c->litros;
                }),
                'cantidad_cargas' => $grupo->count(),
                'precio_promedio' => round($grupo->avg('precio'), 2),
            ];
        })->values();

        return [
            'total_litros' => round($totalLitros, 2),
            'total_costo' => round($totalCosto, 2),
            'cantidad_cargas' => $cargas->count(),
            'vehiculos' => $vehiculosDetalle,
        ];
    }
}
