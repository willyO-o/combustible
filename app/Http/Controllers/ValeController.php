<?php

namespace App\Http\Controllers;

use App\Http\Requests\ValeRequest;
use App\Models\Conductor;
use App\Models\Grifo;
use App\Models\Vale;
use App\Models\Vehiculo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ValeController extends Controller
{
    /* ------------------------------------------------------------------ */
    /*  Endpoints JSON para selects con búsqueda                           */
    /* ------------------------------------------------------------------ */

    public function searchVehiculos(Request $request): JsonResponse
    {
        $q = $request->get('q', '');

        $vehiculos = Vehiculo::where('estado_vehiculo', 'ACTIVO')
            ->where(function ($query) use ($q) {
                $query->where('nro_placa', 'like', "%{$q}%")
                      ->orWhere('marca', 'like', "%{$q}%");
            })
            ->limit(20)
            ->get(['id', 'nro_placa', 'marca', 'anio'])
            ->map(fn($v) => [
                'id'    => $v->id,
                'label' => "{$v->nro_placa}" . ($v->marca ? " — {$v->marca}" : '') . ($v->anio ? " ({$v->anio})" : ''),
            ]);

        return response()->json($vehiculos);
    }

    public function searchConductores(Request $request): JsonResponse
    {
        $q = $request->get('q', '');

        $conductores = Conductor::where('estado_conductor', 'ACTIVO')
            ->where(function ($query) use ($q) {
                $query->where('ci', 'like', "%{$q}%")
                      ->orWhere('nombres', 'like', "%{$q}%")
                      ->orWhere('paterno', 'like', "%{$q}%")
                      ->orWhere('materno', 'like', "%{$q}%");
            })
            ->limit(20)
            ->get(['id', 'ci', 'nombres', 'paterno', 'materno'])
            ->map(fn($c) => [
                'id'    => $c->id,
                'label' => trim("{$c->nombres} {$c->paterno} {$c->materno}") . " (CI: {$c->ci})",
            ]);

        return response()->json($conductores);
    }

    public function searchGrifos(Request $request): JsonResponse
    {
        $q = $request->get('q', '');

        $grifos = Grifo::where('estado_grifo', 'ACTIVO')
            ->where(function ($query) use ($q) {
                $query->where('razon_social', 'like', "%{$q}%")
                      ->orWhere('nit', 'like', "%{$q}%")
                      ->orWhere('ciudad', 'like', "%{$q}%");
            })
            ->limit(20)
            ->get(['id', 'razon_social', 'nit', 'ciudad'])
            ->map(fn($g) => [
                'id'    => $g->id,
                'label' => $g->razon_social . ($g->ciudad ? " — {$g->ciudad}" : '') . " (NIT: {$g->nit})",
            ]);

        return response()->json($grifos);
    }

    /* ------------------------------------------------------------------ */
    /*  CRUD                                                               */
    /* ------------------------------------------------------------------ */

    public function index(Request $request): Response
    {
        $query = Vale::with(['vehiculo', 'conductor', 'grifo']);

        if ($request->filled('nro_vale')) {
            $query->where('nro_vale', $request->nro_vale);
        }
        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_emision', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_emision', '<=', $request->fecha_hasta);
        }
        if ($request->filled('estado_vale')) {
            $query->where('estado_vale', $request->estado_vale);
        }
        if ($request->filled('id_conductor')) {
            $query->where('id_conductor', $request->id_conductor);
        }

        $vales = $query
            ->orderBy('fecha_emision', 'desc')
            ->orderBy('nro_vale', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Vales/Index', [
            'vales'   => $vales,
            'filters' => $request->only(['nro_vale', 'fecha_desde', 'fecha_hasta', 'estado_vale', 'id_conductor']),
            'flash'   => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    public function create(): Response
    {
        $nextNroVale = (Vale::withTrashed()->max('nro_vale') ?? 0) + 1;

        return Inertia::render('Vales/Create', [
            'nextNroVale' => $nextNroVale,
        ]);
    }

    public function store(ValeRequest $request): RedirectResponse
    {
        Vale::create($request->validated());

        return redirect()->route('vales.index')
            ->with('success', "Vale #{$request->nro_vale} registrado exitosamente.");
    }

    public function edit(Vale $vale): Response
    {
        $vale->load(['vehiculo', 'conductor', 'grifo']);

        return Inertia::render('Vales/Edit', [
            'vale' => $vale,
            // Enviamos el objeto completo para que el select muestre el valor actual
            'vehiculoActual'   => $vale->vehiculo  ? ['id' => $vale->vehiculo->id,  'label' => "{$vale->vehiculo->nro_placa}" . ($vale->vehiculo->marca ? " — {$vale->vehiculo->marca}" : '')] : null,
            'conductorActual'  => $vale->conductor ? ['id' => $vale->conductor->id, 'label' => trim("{$vale->conductor->nombres} {$vale->conductor->paterno} {$vale->conductor->materno}") . " (CI: {$vale->conductor->ci})"] : null,
            'grifoActual'      => $vale->grifo     ? ['id' => $vale->grifo->id,     'label' => "{$vale->grifo->razon_social}" . ($vale->grifo->ciudad ? " — {$vale->grifo->ciudad}" : '')] : null,
        ]);
    }

    public function update(ValeRequest $request, Vale $vale): RedirectResponse
    {
        $vale->update($request->validated());

        return redirect()->route('vales.index')
            ->with('success', "Vale #{$vale->nro_vale} actualizado exitosamente.");
    }

    public function destroy(Vale $vale): RedirectResponse
    {
        $vale->delete();

        return redirect()->route('vales.index')
            ->with('success', "Vale #{$vale->nro_vale} eliminado exitosamente.");
    }
}
