<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Actions\Vale\ListValeAction;
use Illuminate\Http\JsonResponse;

class ValeController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function index(Request $request, ListValeAction $listValeAction): JsonResponse
    {
        $filters = $request->only(['nro_vale', 'fecha_desde', 'fecha_hasta', 'estado_vale', 'id_conductor']);

        $vales = $listValeAction->execute($filters, $request->user(), $request->input('per_page', 10), true);

        return response()->json($vales);

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
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
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
