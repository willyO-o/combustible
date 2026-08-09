<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\OperacionDiaria;
use App\Models\Actividad;
use App\Models\Vehiculo;
use Illuminate\Support\Str;


class OperacionDiariaController extends Controller
{


    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $query = OperacionDiaria::with(['conductor.persona', 'vehiculo', 'area', 'verificador']);



        if (auth()->user()->hasRole('conductor')) {
            $query->where('id_conductor', auth()->user()->id_persona);
        }

        if(auth()->user()->hasRole('jefe-area')) {
            $query->whereIn('id_area', auth()->user()->persona->encargadoAreas()->pluck('id_area'));
        }


        $actividades = $query->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        return inertia('Operacion/Index', [
            'actividades' => $actividades,
            'filters'     => $request->only(['estado', 'tipo_carga', 'id_vehiculo']),
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

        try {
            DB::beginTransaction();

            $vehiculo = Vehiculo::findOrFail($request->id_vehiculo);

            $area = $vehiculo->areasAsignadas()->first();

            $actividades = $request->actividades_realizadas;

            $datos = $request->all();
            $datos['id_area'] = $area->id;

            $operacionDiaria = OperacionDiaria::create($datos);


            $this->guardarActividadesRealizadas($operacionDiaria, $actividades);


            // dd($operacionDiaria->actividadesRealizadas());

            DB::commit();

            return redirect()->route('operacion-diaria.index')->with('success', 'Operación diaria creada exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error al crear la operación diaria: ' . $e->getMessage());
        }
    }

    private function guardarActividadesRealizadas(OperacionDiaria $operacion, array $actividades)
    {
        //se busca primero ver si la actividad ya existe en la base de datos verificando el nombre_normalizado, sino existe se crea una nueva actividad
        // se guard en la tabla actividad_realizada, la relacion y los detalles, verificar que no re registre 2 veces la misma actividad contodos los campos iguales

        foreach ($actividades as $actividadData) {
            $nombreNormalizado = Str::of($actividadData['actividad'])
                ->lower()->ascii()->trim();

            $actividad = Actividad::firstOrCreate(
                ['nombre_normalizado' => $nombreNormalizado],
                [
                    'nombre_actividad' => $actividadData['actividad'],
                    'unidad_medida' => $actividadData['unidad_medida'],
                    'id_area' => $operacion->id_area,
                    'estado_actividad' => 'ACTIVO',
                    'ultimo_uso' => now(),

                ]
            );


            $operacion->actividadesRealizadas()->attach($actividad->id, [
                'origen' => $actividadData['origen'],
                'destino' => $actividadData['destino'],
                'lugar' => $actividadData['lugar'],
                'cantidad' => $actividadData['cantidad'],
                'unidad_medida' => $actividadData['unidad_medida'],
                'hora_inicio' => $actividadData['hora_inicio'],
                'hora_fin' => $actividadData['hora_fin'],
            ]);
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

        return redirect()->route('operacion-diaria.create');
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
        $operacion->id_verificador = auth()->user()->id_persona;
        $operacion->save();

        return redirect()->route('operacion-diaria.show', $operacion->id)
            ->with('success', 'Operación diaria verificada exitosamente.');
    }
}
