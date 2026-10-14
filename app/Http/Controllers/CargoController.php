<?php

namespace App\Http\Controllers;

use App\Http\Requests\CargoRequest;
use App\Models\Cargo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catálogo de cargos del personal. Mismo patrón que GrupoVehiculoController:
 * una sola página (Cargos/Index.vue) con modal de alta/edición.
 */
class CargoController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Cargo::withCount('personas')->orderBy('cargo');

        if ($request->filled('cargo')) {
            $query->where('cargo', 'like', '%'.$request->cargo.'%');
        }
        if ($request->filled('estado_cargo')) {
            $query->where('estado_cargo', $request->estado_cargo);
        }

        return Inertia::render('Cargos/Index', [
            // Inertia::scroll() no cambia la forma del paginador: sólo agrega
            // la metadata de merge que usa <InfiniteScroll> (mobile).
            'cargos' => Inertia::scroll($query->paginate(10)->withQueryString()),
            'filters' => $request->only(['cargo', 'estado_cargo']),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function store(CargoRequest $request): RedirectResponse
    {
        $cargo = Cargo::create($request->validated());

        // Con el nombre: Maindashboard sólo muestra el toast cuando el
        // mensaje cambia, y en la página se agregan varios seguidos.
        // back(): la tabla conserva la búsqueda y la página en que estaba.
        return back()->with('success', "Cargo \"{$cargo->cargo}\" registrado.");
    }

    /**
     * Alta rápida desde los formularios de Personas y Conductores (botón
     * "+" junto al selector de cargo): responde JSON con el cargo creado
     * para seleccionarlo sin salir del formulario.
     */
    public function storeRapido(CargoRequest $request): JsonResponse
    {
        $cargo = Cargo::create($request->validated());

        return response()->json([
            'id' => $cargo->id,
            'cargo' => $cargo->cargo,
        ], 201);
    }

    public function update(CargoRequest $request, Cargo $cargo): RedirectResponse
    {
        $cargo->update($request->validated());

        return back()->with('success', "Cargo \"{$cargo->cargo}\" actualizado ({$cargo->estado_cargo}).");
    }

    public function destroy(Cargo $cargo): RedirectResponse
    {
        if ($cargo->personas()->exists()) {
            return back()
                ->with('error', 'No se puede eliminar: hay personas con este cargo. Puede marcarlo como INACTIVO para que ya no se ofrezca.');
        }

        $cargo->delete();

        return back()->with('success', "Cargo \"{$cargo->cargo}\" eliminado.");
    }
}
