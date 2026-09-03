<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CargaCombustible\CreateCargaCombustibleAction;
use App\Actions\CargaCombustible\ListCargaCombustibleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\CargaCombustibleRequest;
use App\Libraries\Reportes;
use App\Models\CargaCombustible;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CargaCombustibleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, ListCargaCombustibleAction $action)
    {
        $filters = $request->only('nro_placa', 'fecha_desde', 'fecha_hasta', 'tipo_carga', 'estado_carga');
        $cargas = $action->execute($filters, $request->user(), $request->input('per_page', 10));

        return response()->json($cargas);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CargaCombustibleRequest $request, CreateCargaCombustibleAction $action): JsonResponse
    {
        // crea un trycatch
        try {

            // La acción necesita la instancia de Request completa (no el arreglo validado):
            // internamente lee id_vale con $request->filled()/->all() y reenvía la request tal
            // cual a SincronizarRespaldoCargaAction para leer los archivos de "respaldos".
            $action->execute($request);

            return response()->json([
                'message' => 'Carga de combustible creada exitosamente',
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al crear la carga de combustible',
                'error' => $e->getMessage(),
            ], 500);
        }

    }

    /**
     * Display the specified resource.
     */
    public function show(CargaCombustible $carga)
    {
        //
        // $carga->load([])
    }

    /**
     * Descarga el comprobante de egreso de combustible en PDF (mismo formato
     * que el sistema web), para verlo/guardarlo desde la app móvil.
     * Un conductor sólo puede descargar el comprobante de sus propias cargas.
     */
    public function pdf(Request $request, CargaCombustible $carga): Response
    {
        if ($request->user()->hasRole('conductor') && $carga->id_conductor !== $request->user()->id_persona) {
            abort(403, 'No tienes permiso para descargar este comprobante.');
        }

        $carga->load(['vehiculo.tipoVehiculo', 'conductor.persona', 'tipoCombustible']);

        $contenido = (new Reportes)->generarComprobanteEgreso($carga, 'S');

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="comprobante_egreso_'.str_replace('/', '-', (string) $carga->nro).'.pdf"',
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
