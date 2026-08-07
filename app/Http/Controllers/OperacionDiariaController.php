<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OperacionDiaria;


class OperacionDiariaController extends Controller
{


    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //

        $conductor = auth()->user()->persona;
        $conductor->load('conductor');
        $vehiculosAsignados = $conductor->conductor->asignacionesActivasOpt();



        return inertia('Operacion/Create', [
            'conductor' => $conductor,
            'vehiculosAsignados' => $vehiculosAsignados,
        ]);
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

    public function validarActividad(Request $request)
    {
        // Validar los campos requeridos
        $validated = $request->validate([
            'actividad' => 'required|string',
            'cantidad' => 'required|numeric',
            'unidad_medida' => 'required|string',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i',
        ]);

        // Agregar la actividad al arreglo de actividades_realizadas

        return redirect()->back();
    }
}
