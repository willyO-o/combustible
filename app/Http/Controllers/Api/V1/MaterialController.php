<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MaterialRequest;
use App\Models\Material;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    /**
     * Lista el catálogo de materiales (cola, broza, concentrado, etc., según
     * lo que maneje cada operación minera), para búsqueda o selección desde
     * la app móvil. También se envía un resumen de este mismo catálogo en
     * GET /parametros/colecciones (control_cargas.materiales).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Material::query();

        if ($request->filled('material')) {
            $query->where('material', 'like', '%'.$request->material.'%');
        }

        return response()->json([
            'data' => $query->orderBy('material')->get(),
        ]);
    }

    /**
     * Registra un material bajo demanda (p.ej. si al registrar un viaje no
     * se encuentra el material buscado en el catálogo), igual que el alta
     * rápida del panel web (MaterialController::store()).
     */
    public function store(MaterialRequest $request): JsonResponse
    {
        $material = Material::create($request->validated());

        return response()->json([
            'message' => 'Material registrado exitosamente.',
            'data' => $material,
        ], 201);
    }
}
