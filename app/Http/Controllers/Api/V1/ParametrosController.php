<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Area;
use App\Models\Actividad;
use App\Models\Grifo;
use App\Models\TipoCombustible;


class ParametrosController extends Controller
{
    //

    public function index(Request $request): JsonResponse
    {
        $parametros = [
            'api_version' => '1.0',
            'app_name' => config('app.name'),
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug'),
            'app_url' => config('app.url'),
            'timezone' => config('app.timezone'),
            'locale' => config('app.locale'),
            'url_logo' => asset('images/logo.png'),
        ];

        return response()->json($parametros);
    }

    public function colecciones(Request $request): JsonResponse
    {
        $conductor = $request->user()->persona->conductor;

        $vehiculos = $conductor->asignacionesActivas->map(function ($vehiculo) {
            return [
                'id' => $vehiculo->id,
                'uuid' => $vehiculo->uuid,
                'nro_placa' => $vehiculo->nro_placa,
                'codigo' => $vehiculo->codigo,
                'anio' => $vehiculo->anio,
                'marca' => $vehiculo->marca,
                'modelo' => $vehiculo->modelo,
                'estado_vehiculo' => $vehiculo->estado_vehiculo,
                'id_tipo_combustible' => $vehiculo->id_tipo_combustible,
                'id_tipo_vehiculo' => $vehiculo->id_tipo_vehiculo,
                'url_fotografia' => $vehiculo->url_fotografia,
                'tipo_medicion' => $vehiculo->tipo_medicion,
            ];
        });


        $areas = $conductor->areas()->pluck('id')->toArray();

        $actividadesSugeridas = Actividad::select('id', 'nombre_actividad', 'unidad_medida')->whereIn('id_area', $areas)->get();


        $asignaciones = [
            'vehiculos' => $vehiculos,
            'estaciones_servicio' => Grifo::where('estado_grifo', 'ACTIVO')->get(),
            'tipos_combustible' => TipoCombustible::where('estado_tipo_combustible', 'ACTIVO')->get(),
            'cargas_combustible' => [
                'tipos_carga' => ["VALE", "PREPAGO"],
                'tipos_respaldo_digital' => ["FACTURA", "NOTA", "COMPROBANTE", "OTRO"],
                'estado_carga' => ['PENDIENTE', 'USADO', 'ANULADO'],
            ],
            'solicitudes_mantenimiento' =>[
                'tipos_mantenimiento' => ["PREVENTIVO", "CORRECTIVO"],
                'estado' => ['PENDIENTE', 'APROBADA', 'RECHAZADA', 'ANULADA']
            ],
            'operaciones_diarias' => [
                'actividades_sugeridas' => $actividadesSugeridas
            ]
        ];

        return response()->json([
            'data' => $asignaciones,
        ]);
    }
}
