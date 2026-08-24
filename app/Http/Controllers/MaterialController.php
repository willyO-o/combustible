<?php

namespace App\Http\Controllers;

use App\Http\Requests\MaterialRequest;
use App\Models\Material;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MaterialController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Material::query();

        if ($request->filled('material')) {
            $query->where('material', 'like', '%'.$request->material.'%');
        }

        $materiales = $query->orderBy('material')->paginate(10)->withQueryString();

        return Inertia::render('Materiales/Index', [
            'materiales' => $materiales,
            'filters' => $request->only(['material']),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    /**
     * Registra un material. Además de la página del módulo, este endpoint
     * también es usado por el componente reutilizable MaterialFormModal
     * desde otros formularios (ej. cargas de material): cuando la petición
     * viene por AJAX se responde en JSON con el material creado en vez de
     * redirigir, para que el formulario que lo invocó pueda seguir en la
     * misma pantalla y seleccionar el material recién creado.
     */
    public function store(MaterialRequest $request): RedirectResponse|JsonResponse
    {
        $material = Material::create($request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Material registrado exitosamente.',
                'data' => $material,
            ]);
        }

        return redirect()->route('materiales.index')
            ->with('success', 'Material registrado exitosamente.');
    }

    public function update(MaterialRequest $request, Material $material): RedirectResponse|JsonResponse
    {
        $material->update($request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Material actualizado exitosamente.',
                'data' => $material,
            ]);
        }

        return redirect()->route('materiales.index')
            ->with('success', 'Material actualizado exitosamente.');
    }

    public function destroy(Material $material): RedirectResponse
    {
        if (DB::table('viaje')->where('id_material', $material->id)->exists()) {
            return redirect()->route('materiales.index')
                ->with('error', 'No se puede eliminar: existen viajes registrados con este material.');
        }

        $material->delete();

        return redirect()->route('materiales.index')
            ->with('success', 'Material eliminado exitosamente.');
    }
}
