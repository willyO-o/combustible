<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\OperacionStoreRequest;
use Illuminate\Support\Facades\DB;
use App\Models\OperacionDiaria;
use App\Models\Actividad;
use App\Models\Vehiculo;
use App\Models\Area;
use Illuminate\Support\Str;
use App\Exceptions\AreaNoAsignadaException;
use App\Actions\OperacionDiaria\CreateOperacionDiariaAction;
use App\Actions\OperacionDiaria\UpdateOperacionDiariaAction;
use App\Actions\OperacionDiaria\ListOperacionesDiariasAction;


class OperacionDiariaController extends Controller
{


    /**
     * Display a listing of the resource.
     */
    public function index(
        Request $request,
        ListOperacionesDiariasAction $listAction
    ) {

        $filters = $request->only(['nro_placa', 'fecha_desde', 'fecha_hasta', 'id_conductor', 'estado_operacion']);

        $actividades = $listAction->execute($filters, $request->user());

        $conductores = [];

        $areas = [];

        if ($request->user()->hasRole('conductor')) {
            $areas = false;
        }

        if ($request->user()->hasRole('jefe-area')) {
            $areas = $request->user()->persona->encargadoAreas()->pluck('id_area')->toArray();
        }

        $conductores = Area::conductores($areas)?->map(function ($conductor) {
            return [
                'id' => $conductor->id,
                'label' => "{$conductor->persona->nombre_completo} (CI: {$conductor->persona->ci})",
            ];
        })->toArray();


        return inertia('Operacion/Index', [
            'actividades' => $actividades,
            'filters'     => $filters,
            'conductores' => $conductores,
            'flash'       => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //

        $conductor = request()->user()->persona;
        $conductor->load('conductor');
        $vehiculosAsignados = $conductor->conductor->asignacionesActivasOpt();



        return inertia('Operacion/Create', [
            'conductor' => $conductor,
            'vehiculosAsignados' => $vehiculosAsignados,
            'operacion' => null,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(OperacionStoreRequest $request, CreateOperacionDiariaAction $action)
    {

        try {
            $operacionDiaria = $action->execute($request->validated());

            return redirect()->route('operacion-diaria.index')->with('success', 'Operación diaria creada exitosamente.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al crear la operación diaria: ' . $e->getMessage());
        }
    }



    /**
     * Display the specified resource.
     */
    public function show(OperacionDiaria $operacionDiaria)
    {

        $operacion = $operacionDiaria->load(['conductor.persona', 'vehiculo', 'area', 'verificador', 'actividadesRealizadas']);


        return inertia('Operacion/Show', [
            'operacion' => $operacion,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(OperacionDiaria $operacionDiaria)
    {

        $conductor = request()->user()->persona;
        $conductor->load('conductor');
        $vehiculosAsignados = $conductor->conductor->asignacionesActivasOpt();

        $operacionDiaria->actividades_realizadas_edit = $operacionDiaria->actividadesRealizadasEdit();

        return inertia('Operacion/Create', [
            'conductor' => $conductor,
            'vehiculosAsignados' => $vehiculosAsignados,
            'operacion' => $operacionDiaria->load(['vehiculo', 'area']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(OperacionStoreRequest $request, OperacionDiaria $operacionDiaria, UpdateOperacionDiariaAction $action)
    {
        try {
            $operacionDiaria = $action->execute($operacionDiaria, $request->validated());

            return redirect()->route('operacion-diaria.index')->with('success', 'Operación diaria actualizada exitosamente.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al actualizar la operación diaria: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(OperacionDiaria $operacionDiaria)
    {
        if ($operacionDiaria->estado === 'VERIFICADO') {
            return redirect()->back()->with('error', 'No se puede eliminar una operación diaria que ya ha sido verificada.');
        }

        try {

            $operacionDiaria->actividadesRealizadas()->detach();

            $operacionDiaria->delete();


            return redirect()->route('operacion-diaria.index')->with('success', 'Operación diaria eliminada exitosamente.');
        } catch (\Exception $e) {

            return redirect()->back()->with('error', 'Error al eliminar la operación diaria: ' . $e->getMessage());
        }
    }

    public function validarActividad(Request $request)
    {
        // Validar los campos requeridos
        $validated = $request->validate([
            'lugar' => 'required_without:origen|nullable|string',
            'origen' => 'required_without:lugar|nullable|string',
            'destino' => 'required_without:lugar|nullable|string',
            'actividad' => 'required|string|min:3',
            'cantidad' => 'required|numeric|min:1',
            'unidad_medida' => 'required|string',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i',
        ]);

        // Agregar la actividad al arreglo de actividades_realizadas

        return redirect()->route('operacion-diaria.create')->with('success', 'Actividad agregada exitosamente.');
    }


    public function generarPDF(OperacionDiaria $operacionDiaria)
    {
        $operacion = $operacionDiaria->load(['conductor.persona', 'vehiculo', 'area', 'verificador', 'actividadesRealizadas']);

        $reporte = new \App\Libraries\Reportes();

        $reporte->generarReporteOperacionDiaria($operacion);
        exit;
    }

    public function verificarOperacion(Request $request)
    {
        $operacion = OperacionDiaria::findOrFail($request->id_operacion);
        $operacion->estado = 'VERIFICADO';
        $operacion->id_verificador = request()->user()->id_persona;
        $operacion->save();

        return redirect()->route('operacion-diaria.show', $operacion->id)
            ->with('success', 'Operación diaria verificada exitosamente.');
    }
}
