<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Actions\CargaCombustible\CreateCargaCombustibleAction;
use App\Actions\CargaCombustible\ListCargaCombustibleAction;
use App\Http\Requests\CargaCombustibleRequest;
use App\Models\CargaCombustible;
use Exception;
use Illuminate\Http\JsonResponse;

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
    public function store(CargaCombustibleRequest $request, CreateCargaCombustibleAction $action ): JsonResponse
    {
        //crea un trycatch
        try{

            $datos = $request->validated();
            $action->execute($datos);

            return response()->json([
                'message' => 'Carga de combustible creada exitosamente',
            ], 201);



        }catch (Exception $e){
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
