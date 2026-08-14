<?php

namespace App\Http\Controllers;

use App\Http\Requests\ValeRequest;
use App\Models\Conductor;
use App\Models\Grifo;
use App\Models\Vale;
use App\Models\Vehiculo;
use App\Models\TipoCombustible;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

use App\Libraries\Reportes;

class ValeController extends Controller
{


    /* ------------------------------------------------------------------ */
    /*  CRUD                                                               */
    /* ------------------------------------------------------------------ */

    public function index(Request $request): Response
    {
        $query = Vale::with(['vehiculo', 'conductor.persona', 'grifo']);

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
        if ($request->user()->hasRole('conductor')) {
            $idConductor = $request->user()->id_persona;
            $query->where('id_conductor', $idConductor);
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
        $nextNroVale = Vale::siguienteNroValeProvisional();

        $tiposCombustible = TipoCombustible::where('estado_tipo_combustible', 'ACTIVO')
            ->orderBy('tipo_combustible', 'asc')
            ->get(['id', 'tipo_combustible'])
            ->map(fn($t) => [
                'id'    => $t->id,
                'label' => $t->tipo_combustible,
            ]);

        $grifos = Grifo::where('estado_grifo', 'ACTIVO')
            ->get(['id', 'razon_social', 'ciudad']);


        return Inertia::render('Vales/Create', [
            'nextNroVale' => $nextNroVale,
            'tiposCombustible' => $tiposCombustible,
            'grifos' => $grifos,
            'vale' => null,
        ]);
    }

    public function store(ValeRequest $request): RedirectResponse
    {
        $vale = Vale::create($request->validated());

        return redirect()->route('vales.index')
            ->with('success', "Vale #{$vale->nro} registrado exitosamente.");
    }

    public function edit(Vale $vale): Response
    {
        $vale->load(['vehiculo', 'conductor', 'grifo']);


        $tiposCombustible = TipoCombustible::where('estado_tipo_combustible', 'ACTIVO')
            ->orderBy('tipo_combustible', 'asc')
            ->get(['id', 'tipo_combustible'])
            ->map(fn($t) => [
                'id'    => $t->id,
                'label' => $t->tipo_combustible,
            ]);


        $grifos = Grifo::where('estado_grifo', 'ACTIVO')
            ->get(['id', 'razon_social', 'ciudad']);


        return Inertia::render('Vales/Create', [
            'vale' => $vale,
            // Enviamos el objeto completo para que el select muestre el valor actual
            'vehiculoActual'   => $vale->vehiculo  ? ['id' => $vale->vehiculo->id,  'label' => "{$vale->vehiculo->codigo} — {$vale->vehiculo->nro_placa}" . ($vale->vehiculo->marca ? " — {$vale->vehiculo->marca}" : '')] : null,
            'conductorActual'  => $vale->conductor ? ['id' => $vale->conductor->id, 'label' => trim("{$vale->conductor->persona->nombre_completo}") . " (CI: {$vale->conductor->persona->ci})"] : null,
            'tiposCombustible' => $tiposCombustible,
            'grifos' => $grifos,
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


    /* ------------------------------------------------------------------ */
    /*  Endpoints JSON para selects con búsqueda                           */
    /* ------------------------------------------------------------------ */

    public function searchVehiculos(Request $request): JsonResponse
    {
        $q = $request->input('q', '');

        $vehiculos = Vehiculo::with('conductorAsignado')->where('estado_vehiculo', 'ACTIVO')
            ->where(function ($query) use ($q) {
                $query->where('nro_placa', 'like', "%{$q}%")
                    ->orWhere('marca', 'like', "%{$q}%");
            })
            ->limit(20)
            ->get()
            ->map(fn($v) => [
                'id'    => $v->id,
                'label' => "{$v->nro_placa}" . ($v->marca ? " — {$v->marca}" : '') . ($v->anio ? " ({$v->anio})" : ''),
                'meta'  => [
                    'id_conductor' => $v->conductorAsignado ? $v->conductorAsignado->id : null,
                    'id_tipo_vehiculo' => $v->id_tipo_vehiculo,
                    'id_tipo_combustible' => $v->id_tipo_combustible,
                    'tipo_medicion' => $v->tipo_medicion,
                    'marca' => $v->marca,
                    'nro_placa' => $v->nro_placa,
                    'anio' => $v->anio,
                ],
            ]);

        return response()->json($vehiculos);
    }

    public function searchConductores(Request $request): JsonResponse
    {
        $conductores = [];
        if ($request->filled('id_vehiculo')) {
            $vehiculo = Vehiculo::find($request->id_vehiculo);
            $conductores = $vehiculo->conductorAsignado()
                ->join('persona', 'persona.id', '=', 'conductor.id')
                ->select(
                    'conductor.id',
                    'persona.ci',
                    'persona.nombres',
                    'persona.paterno',
                    'persona.materno'
                )
                ->get()
                ->map(function ($c) {
                    return [
                        'id' => $c->id,
                        'label' => trim(
                            "{$c->nombres} {$c->paterno} {$c->materno}"
                        ) . " (CI: {$c->ci})",
                        'meta' => []
                    ];
                });
        } else {

            $q = $request->input('q', '');

            $conductores = Conductor::join('persona', 'conductor.id', '=', 'persona.id')
                ->where('estado_conductor', 'ACTIVO')
                ->where(function ($query) use ($q) {
                    $query->where('persona.ci', 'like', "%{$q}%")
                        ->orWhere('persona.nombres', 'like', "%{$q}%")
                        ->orWhere('persona.paterno', 'like', "%{$q}%")
                        ->orWhere('persona.materno', 'like', "%{$q}%")
                        ->orWhereRaw("CONCAT(persona.nombres, ' ', COALESCE(persona.paterno, ''), ' ', COALESCE(persona.materno, '')) like ?", ["%{$q}%"]);
                })
                ->limit(20)
                ->get(['persona.id', 'persona.ci', 'persona.nombres', 'persona.paterno', 'persona.materno'])
                ->map(fn($c) => [
                    'id'    => $c->id,
                    'label' => trim("{$c->nombres} {$c->paterno} {$c->materno}") . " (CI: {$c->ci})",
                    'meta'  => []
                ]);
        }

        return response()->json($conductores);
    }

    public function searchGrifos(Request $request): JsonResponse
    {
        $q = $request->input('q', '');

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


    public function detalle(Vale $vale): JsonResponse
    {
        $vale->load([
            'vehiculo',
            'conductor.persona',
            'grifo',
            'tipoCombustible',
            'user',
            'cargasCombustible.grifo',
            'cargasCombustible.conductor.persona',
        ]);

        $total = round($vale->litros * $vale->precio, 2);

        $carga = null;
        if ($vale->estado_vale === 'USADO' && $vale->cargasCombustible->isNotEmpty()) {
            $c = $vale->cargasCombustible->first();
            $carga = [
                'id'             => $c->id,
                'fecha_carga'    => $c->fecha_carga?->format('d/m/Y'),
                'litros'         => $c->litros,
                'precio'         => $c->precio,
                'total'          => round($c->litros * $c->precio, 2),
                'nro_factura'    => $c->nro_factura,
                'kilometraje'    => $c->kilometraje,
                'horometro'      => $c->horometro,
                'tipo_carga'     => $c->tipo_carga,
                'estado_carga'   => $c->estado_carga,
                'grifo'          => $c->grifo ? [
                    'razon_social' => $c->grifo->razon_social,
                    'ciudad'       => $c->grifo->ciudad,
                ] : null,
            ];
        }

        return response()->json([
            'id'                => $vale->id,
            'nro'               => $vale->nro,
            'fecha_emision'     => $vale->fecha_emision_f,
            'litros'            => $vale->litros,
            'precio'            => $vale->precio,
            'total'             => $total,
            'estado_vale'       => $vale->estado_vale,
            'tipo_combustible'  => $vale->tipoCombustible?->tipo_combustible,
            'vehiculo'          => $vale->vehiculo ? [
                'nro_placa' => $vale->vehiculo->nro_placa,
                'marca'     => $vale->vehiculo->marca,
                'anio'      => $vale->vehiculo->anio,
                'modelo'    => $vale->vehiculo->modelo ?? null,
            ] : null,
            'conductor'         => $vale->conductor ? [
                'nombre_completo' => trim("{$vale->conductor->persona->nombres} {$vale->conductor->persona->paterno} {$vale->conductor->persona->materno}"),
                'ci'              => $vale->conductor->persona->ci,
            ] : null,
            'grifo'             => $vale->grifo ? [
                'razon_social' => $vale->grifo->razon_social,
                'ciudad'       => $vale->grifo->ciudad,
                'nit'          => $vale->grifo->nit,
            ] : null,
            'registrado_por'    => $vale->user?->name,
            'carga'             => $carga,
        ]);
    }

    public function imprimirVale(Vale $vale)
    {
        // dd($vale);
        $vale->load(['vehiculo', 'conductor.persona', 'grifo', 'tipoCombustible', 'user']);

        $reporte = new Reportes();
        $reporte->generarVale($vale);

        exit;

        // return response()->json(['message' => 'PDF generado correctamente.']);

    }
}
