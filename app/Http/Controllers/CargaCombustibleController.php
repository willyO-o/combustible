<?php

namespace App\Http\Controllers;

use App\Http\Requests\CargaCombustibleRequest;
use App\Models\CargaCombustible;
use App\Models\Grifo;
use App\Models\RespaldoDigital;
use App\Models\TipoCombustible;
use App\Models\Vale;
use App\Models\Vehiculo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CargaCombustibleController extends Controller
{
    /* ------------------------------------------------------------------ */
    /*  Endpoints JSON                                                      */
    /* ------------------------------------------------------------------ */

    /** Info del vehículo: tipo_combustible + conductorAsignado ACTIVO */
    public function vehiculoInfo(int $id): JsonResponse
    {
        $vehiculo = Vehiculo::with('tipoCombustible')->findOrFail($id);
        $conductor = $vehiculo->conductorAsignado;

        return response()->json([
            'tipo_combustible' => $vehiculo->tipoCombustible ? [
                'id'    => $vehiculo->tipoCombustible->id,
                'label' => $vehiculo->tipoCombustible->tipo_combustible,
            ] : null,
            'conductor' => $conductor ? [
                'id'    => $conductor->id,
                'label' => trim("{$conductor->nombres} {$conductor->paterno} {$conductor->materno}")
                           . " (CI: {$conductor->ci})",
            ] : null,
        ]);
    }

    /** Búsqueda de vales PENDIENTE (opcional filtrar por vehículo) */
    public function searchVales(Request $request): JsonResponse
    {
        $q          = $request->get('q', '');
        $idVehiculo = $request->get('id_vehiculo');

        $query = Vale::where('estado_vale', 'PENDIENTE');

        if ($idVehiculo) {
            $query->where('id_vehiculo', $idVehiculo);
        }
        if ($q) {
            $query->where('nro_vale', 'like', "%{$q}%");
        }

        $vales = $query->limit(20)->get(['id', 'nro_vale', 'fecha_emision', 'litros'])
            ->map(fn($v) => [
                'id'    => $v->id,
                'label' => "Vale #{$v->nro_vale} — {$v->litros} Lt ({$v->fecha_emision->format('d/m/Y')})",
            ]);

        return response()->json($vales);
    }

    /* ------------------------------------------------------------------ */
    /*  CRUD                                                               */
    /* ------------------------------------------------------------------ */

    public function index(Request $request): Response
    {
        $query = CargaCombustible::with(['vehiculo', 'conductor', 'grifo', 'tipoCombustible', 'vale']);

        if ($request->filled('nro_placa')) {
            $query->whereHas('vehiculo', fn($q) => $q->where('nro_placa', 'like', '%' . $request->nro_placa . '%'));
        }
        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_carga', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_carga', '<=', $request->fecha_hasta);
        }
        if ($request->filled('tipo_carga')) {
            $query->where('tipo_carga', $request->tipo_carga);
        }
        if ($request->filled('estado_carga')) {
            $query->where('estado_carga', $request->estado_carga);
        }

        $cargas = $query
            ->orderBy('fecha_carga', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('CargasCombustible/Index', [
            'cargas'  => $cargas,
            'filters' => $request->only(['nro_placa', 'fecha_desde', 'fecha_hasta', 'tipo_carga', 'estado_carga']),
            'flash'   => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('CargasCombustible/Create', [
            'tiposCombustible' => TipoCombustible::where('estado_tipo_combustible', 'ACTIVO')
                ->orderBy('tipo_combustible')->get(['id', 'tipo_combustible']),
            'grifos' => Grifo::where('estado_grifo', 'ACTIVO')
                ->orderBy('razon_social')
                ->get(['id', 'razon_social', 'ciudad'])
                ->map(fn($g) => [
                    'id'    => $g->id,
                    'label' => $g->razon_social . ($g->ciudad ? " — {$g->ciudad}" : ''),
                ]),
        ]);
    }

    public function store(CargaCombustibleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['respaldo_count']);

        $carga = CargaCombustible::create($data);

        $this->processRespaldos($request, $carga);

        return redirect()->route('cargas.index')
            ->with('success', 'Carga de combustible registrada exitosamente.');
    }

    public function edit(CargaCombustible $carga): Response
    {
        $carga->load(['vehiculo.tipoCombustible', 'conductor', 'grifo', 'tipoCombustible', 'vale', 'respaldosDigitales']);

        return Inertia::render('CargasCombustible/Edit', [
            'carga' => $carga,
            'tiposCombustible' => TipoCombustible::where('estado_tipo_combustible', 'ACTIVO')
                ->orderBy('tipo_combustible')->get(['id', 'tipo_combustible']),
            'grifos' => Grifo::where('estado_grifo', 'ACTIVO')
                ->orderBy('razon_social')
                ->get(['id', 'razon_social', 'ciudad'])
                ->map(fn($g) => [
                    'id'    => $g->id,
                    'label' => $g->razon_social . ($g->ciudad ? " — {$g->ciudad}" : ''),
                ]),
            // Objetos actuales para los SearchSelects
            'vehiculoActual'  => $carga->vehiculo  ? ['id' => $carga->vehiculo->id,   'label' => "{$carga->vehiculo->nro_placa}" . ($carga->vehiculo->marca ? " — {$carga->vehiculo->marca}" : '')] : null,
            'conductorActual' => $carga->conductor ? ['id' => $carga->conductor->id,  'label' => trim("{$carga->conductor->nombres} {$carga->conductor->paterno} {$carga->conductor->materno}") . " (CI: {$carga->conductor->ci})"] : null,
            'valeActual'      => $carga->vale      ? ['id' => $carga->vale->id,       'label' => "Vale #{$carga->vale->nro_vale} — {$carga->vale->litros} Lt"] : null,
        ]);
    }

    public function update(CargaCombustibleRequest $request, CargaCombustible $carga): RedirectResponse
    {
        $data = $request->validated();
        unset($data['respaldo_count']);

        $carga->update($data);

        // Eliminar respaldos marcados
        $eliminar = $request->input('respaldos_eliminar', []);
        foreach ($eliminar as $respaldoId) {
            $respaldo = RespaldoDigital::find($respaldoId);
            if ($respaldo && $respaldo->id_carga_combustible === $carga->id) {
                Storage::disk('public')->delete($respaldo->ruta_respaldo);
                $respaldo->delete();
            }
        }

        $this->processRespaldos($request, $carga);

        return redirect()->route('cargas.index')
            ->with('success', 'Carga de combustible actualizada exitosamente.');
    }

    public function destroy(CargaCombustible $carga): RedirectResponse
    {
        // Eliminar respaldos físicos
        foreach ($carga->respaldosDigitales as $respaldo) {
            Storage::disk('public')->delete($respaldo->ruta_respaldo);
            $respaldo->delete();
        }

        $carga->delete();

        return redirect()->route('cargas.index')
            ->with('success', 'Carga de combustible eliminada exitosamente.');
    }

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                             */
    /* ------------------------------------------------------------------ */

    private function processRespaldos(Request $request, CargaCombustible $carga): void
    {
        $count = (int) $request->input('respaldo_count', 0);

        for ($i = 0; $i < $count; $i++) {
            if (!$request->hasFile("respaldo_archivo_{$i}")) {
                continue;
            }

            $archivo     = $request->file("respaldo_archivo_{$i}");
            $tipoArchivo = str_starts_with($archivo->getMimeType(), 'image/') ? 'IMAGEN' : 'PDF';
            $ruta        = $archivo->store("respaldos/{$carga->id}", 'public');

            RespaldoDigital::create([
                'ruta_respaldo'        => $ruta,
                'tipo_respaldo'        => $request->input("respaldo_tipo_{$i}", 'OTRO'),
                'tipo_archivo'         => $tipoArchivo,
                'id_carga_combustible' => $carga->id,
                'id_incidencia'        => null,
            ]);
        }
    }
}
