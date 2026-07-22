<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Conductor;
use App\Models\Vale;
use App\Models\CargaCombustible;
use App\Models\Vehiculo;

class DashboardController extends Controller
{
    //
    public function index()
    {
        $conductores['total'] = Conductor::count();
        $conductores['porcentaje'] = "+" . (Conductor::where('created_at', '>=', now()->subMonth())->count() / $conductores['total'] * 100) . "%";



        $vales['total'] = CargaCombustible::whereNotNull('id_vale')->count();
        $vales['porcentaje'] = $vales['total'] ? "+" . (CargaCombustible::whereNotNull('id_vale')
            ->whereMonth('fecha_carga', now()->subMonth()->month)
            ->whereYear('fecha_carga', now()->subMonth()->year)
            ->count() / $vales['total'] * 100) . "%" : "+0%";


            // dd($vales['total'], $vales['porcentaje'], now()->subMonth()->month, now()->subMonth()->year);

        $cargas['total'] = CargaCombustible::whereMonth('fecha_carga', now()->month)
            ->whereYear('fecha_carga', now()->year)
            ->count();
        $cargas['porcentaje'] = $cargas['total'] ? "+" . (CargaCombustible::whereMonth('fecha_carga', now()->subMonth()->month)
            ->whereYear('fecha_carga', now()->subMonth()->year)
            ->count() / $cargas['total'] * 100) . "%" : "+0%";

        $vehiculos['total'] = Vehiculo::count();

        $vehiculos['porcentaje'] = "+" . (Vehiculo::where('created_at', '>=', now()->subMonth())->count() / $vehiculos['total'] * 100) . "%";

        $reporteMes = CargaCombustible::reporteCargaCombustibleMes();

        return Inertia::render('Dashboard', [
            'conductores' => $conductores,
            'vales' => $vales,
            'cargas' => $cargas,
            'vehiculos' => $vehiculos,
            'reporteMes' => $reporteMes,
        ]);

    }
}
