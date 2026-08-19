<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Vale\ListValeAction;
use App\Http\Controllers\Controller;
use App\Libraries\Reportes;
use App\Models\Vale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

    public function valesPendientes(Request $request, ListValeAction $listValeAction)
    {
        $vales = $listValeAction->execute([], $request->user(), $request->input('per_page', 10), true);

        // modificar los campos pendientes para que solo se muestren los que tienen estado pendiente y fecha de vencimiento mayor o igual a la fecha actual
        $valesPendientes = $vales->map(function ($vale) {
            return [
                'id' => $vale->id,
                'nro_vale' => $vale->nro_vale,
                'nro' => $vale->nro,
                'gestion' => $vale->gestion,
                'fecha_emision' => $vale->fecha_emision->format('Y-m-d H:i'),
                'fecha_vencimiento' => $vale->fecha_vencimiento->format('Y-m-d H:i'),
                'litros' => $vale->litros,
                'precio' => $vale->precio,
                'id_vehiculo' => $vale->id_vehiculo,
                'id_conductor' => $vale->id_conductor,
                'id_grifo' => $vale->id_grifo,
                'estado_vale' => $vale->estado_vale,
                'id_tipo_combustible' => $vale->id_tipo_combustible,
                'id_user' => $vale->id_user,
                'grifo' => [
                    'id' => 3,
                    'razon_social' => 'Combustibles Oruro S.R.L.',
                    'ciudad' => 'Oruro',
                ],
            ];
        });

        return response()->json([
            'data' => $valesPendientes,
        ]);
    }

    /**
     * Descarga el PDF del vale para verlo/guardarlo desde la app móvil.
     * Un conductor sólo puede descargar sus propios vales.
     */
    public function pdf(Request $request, Vale $vale): Response
    {
        if ($request->user()->hasRole('conductor') && $vale->id_conductor !== $request->user()->id_persona) {
            abort(403, 'No tienes permiso para descargar este vale.');
        }

        $vale->load(['vehiculo', 'conductor.persona', 'grifo', 'tipoCombustible', 'user']);

        $contenido = (new Reportes)->generarVale($vale, 'S');

        // $vale->nro tiene formato "NNNNNN/GESTION"; el '/' no es válido dentro de
        // un nombre de archivo, así que se reemplaza por '-' sólo para el header.
        $nombreArchivo = 'vale_'.str_replace('/', '-', $vale->nro).'.pdf';

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nombreArchivo.'"',
        ]);
    }
}
