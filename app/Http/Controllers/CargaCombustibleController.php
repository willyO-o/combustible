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
use App\Actions\CargaCombustible\CreateCargaCombustibleAction;
use App\Actions\CargaCombustible\ListCargaCombustibleAction;

class CargaCombustibleController extends Controller
{

    /* ------------------------------------------------------------------ */
    /*  CRUD                                                               */
    /* ------------------------------------------------------------------ */

    public function index(Request $request, ListCargaCombustibleAction $listCargaCombustibleAction): Response
    {
        $filters = $request->only(['nro_placa', 'fecha_desde', 'fecha_hasta', 'tipo_carga', 'estado_carga']);

        $cargas = $listCargaCombustibleAction->execute($filters, $request->user(), $request->input('per_page', 10));

        return Inertia::render('CargasCombustible/Index', [
            'cargas'  => $cargas,
            'filters' => $filters,
            'flash'   => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    public function create(): Response
    {
        $tiposCombustible = TipoCombustible::where('estado_tipo_combustible', 'ACTIVO')
            ->orderBy('tipo_combustible')->get(['id', 'tipo_combustible']);

        $grifos = Grifo::where('estado_grifo', 'ACTIVO')
            ->orderBy('razon_social')
            ->get(['id', 'razon_social', 'ciudad'])
            ->map(fn($g) => [
                'id'    => $g->id,
                'label' => $g->razon_social . ($g->ciudad ? " — {$g->ciudad}" : ''),
            ]);

        // verificar si el rol es conductor y obtener el conductor asignado al usuario autenticado
        $conductor = null;
        $vehiculosAsignados = [];
        $valesConductor = [];
        if (request()->user()->hasRole('conductor')) {
            $conductor = request()->user()->persona;
            $conductor->load('conductor');


            $vehiculosAsignados = $conductor->conductor->asignacionesActivasOpt();

            $valesConductor = Vale::where('estado_vale', 'PENDIENTE')
                ->where('id_conductor', $conductor->id)
                ->orderBy('fecha_emision', 'desc')
                ->get()->map(fn($v) => [
                    'id'    => $v->id,
                    'label' => "Vale #{$v->nro} — {$v->litros} Lt ({$v->fecha_emision_f})",
                    'meta'  => [
                        'litros'        => $v->litros,
                        'fecha_emision' => $v->fecha_emision_f,
                        'precio'        => $v->precio,
                        'id_grifo'      => $v->id_grifo,
                    ]
                ]);
        }

        // dd($conductor->conductor->asignacioneActivas);

        return Inertia::render('CargasCombustible/Create', [
            'tiposCombustible' => $tiposCombustible,
            'grifos' => $grifos,
            'conductor' => $conductor,
            'valesConductor' => $valesConductor,
            'vehiculosAsignados' => $vehiculosAsignados,
        ]);
    }

    public function store(CargaCombustibleRequest $request, CreateCargaCombustibleAction $action): RedirectResponse
    {

        try {
            $carga = $action->execute($request);

            return redirect()->route('cargas.index')
                ->with('success', 'Carga de combustible registrada exitosamente.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al registrar la carga de combustible: ' . $e->getMessage());
        }
    }

    public function edit(CargaCombustible $carga): Response
    {
        $carga->load(['vehiculo.tipoCombustible', 'vehiculo', 'conductor', 'grifo', 'tipoCombustible', 'vale', 'respaldosDigitales']);

        $conductor = null;
        if (request()->user()->hasRole('conductor')) {
            $conductor = request()->user()->persona;
            $conductor->load('conductor');
        }

        // dd($carga->vehiculo->conductoresAsignados);
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
            'conductores' => $carga->vehiculo->conductoresAsignadosOpt(),
            // Objetos actuales para los SearchSelects
            'vehiculoActual'  => $carga->vehiculo  ? [
                'id' => $carga->vehiculo->id,
                'label' => "{$carga->vehiculo->nro_placa}" . " — {$carga->vehiculo->marca} ({$carga->vehiculo->anio})",
                'meta' => [
                    'id_tipo_combustible' => $carga->vehiculo->id_tipo_combustible,
                    'tipo_medicion' => $carga->vehiculo->tipo_medicion

                ]
            ] : null,
            'conductorActual' => $carga->conductor ? ['id' => $carga->conductor->id,  'label' => trim("{$carga->conductor->persona->nombre_completo}") . " (CI: {$carga->conductor->persona->ci})"] : null,
            'valeActual'      => $carga->vale      ? ['id' => $carga->vale->id,       'label' => "Vale #{$carga->vale->nro_vale} — {$carga->vale->litros} Lt"] : null,
            'conductor' => $conductor,
        ]);
    }

    public function update(CargaCombustibleRequest $request, CargaCombustible $carga,): RedirectResponse
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

    private function processRespaldos1(Request $request, CargaCombustible $carga): void
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
    private function processRespaldos(Request $request, CargaCombustible $carga): void
    {
        //capturar respaldos
        $respaldos = $request->input('respaldos', []);
        // dd($respaldos, $request->file('respaldos', []));


        foreach ($request->file('respaldos', []) as $index => $archivo) {
            if (!$archivo) {
                continue;
            }

            // dd($archivo["archivo"]->getMimeType());

            $tipoArchivo = str_starts_with($archivo["archivo"]->getMimeType(), 'image/') ? 'IMAGEN' : 'PDF';
            $ruta        = $archivo["archivo"]->store("respaldos/{$tipoArchivo}", 'public');

            RespaldoDigital::create([
                'ruta_respaldo'        => $ruta,
                'tipo_respaldo'        => $respaldos[$index]['tipo'] ?? 'OTRO',
                'tipo_archivo'         => $tipoArchivo,
                'id_carga_combustible' => $carga->id,
            ]);
        }
    }

        /* ------------------------------------------------------------------ */
    /*  Endpoints JSON                                                      */
    /* ------------------------------------------------------------------ */

    /** Info del vehículo: tipo_combustible + conductorAsignado ACTIVO */
    public function vehiculoInfo(int $id): JsonResponse
    {
        $vehiculo = Vehiculo::with(['tipoCombustible'])->findOrFail($id);
        $conductor = $vehiculo->conductorAsignado;


        return response()->json([
            'tipo_combustible' => $vehiculo->tipoCombustible ? [
                'id'    => $vehiculo->tipoCombustible->id,
                'label' => $vehiculo->tipoCombustible->tipo_combustible,
            ] : null,
            'conductor' => $conductor ? [
                'id'    => $conductor->id,
                'label' => trim("{$conductor->persona->nombres} {$conductor->persona->paterno} {$conductor->persona->materno}")
                    . " (CI: {$conductor->persona->ci})",
            ] : null,
        ]);
    }

    /** Búsqueda de vales PENDIENTE (opcional filtrar por vehículo) */
    public function searchVales(Request $request): JsonResponse
    {
        $q          = $request->input('q', '');
        $idVehiculo = $request->input('id_vehiculo');

        if (!$q && !$idVehiculo) {
            return response()->json([]);
        }
        $query = Vale::where('estado_vale', 'PENDIENTE');

        if ($idVehiculo) {
            $query->where('id_vehiculo', $idVehiculo);
        }
        if ($q) {
            $nro = (int) $q;
            $query->where('nro_vale', 'like', "%{$q}%")
                ->orWhere('nro_vale', 'like', "%{$nro}%");
        }

        if (request()->user()->hasRole('conductor')) {
            $idConductor = request()->user()->persona->id;
            $query->where('id_conductor', $idConductor);
        }

        $vales = $query->limit(20)->get()
            ->map(fn($v) => [
                'id'    => $v->id,
                'label' => "Vale #{$v->nro} — {$v->litros} Lt ({$v->fecha_emision_f})",
                'meta' => [
                    'litros' => $v->litros,
                    'fecha_emision' => $v->fecha_emision_f,
                    'precio' => $v->precio,
                    'id_grifo' => $v->id_grifo,
                ]
            ]);

        return response()->json($vales);
    }
}
