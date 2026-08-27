<?php

namespace App\Http\Controllers;

use App\Http\Requests\TipoCombustibleRequest;
use App\Models\TipoCombustible;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TipoCombustibleController extends Controller
{
    public function index(Request $request): Response
    {
        $query = TipoCombustible::query();

        if ($request->filled('tipo_combustible')) {
            $query->where('tipo_combustible', 'like', '%'.$request->tipo_combustible.'%');
        }
        if ($request->filled('estado_tipo_combustible')) {
            $query->where('estado_tipo_combustible', $request->estado_tipo_combustible);
        }

        $tipos = $query->orderBy('tipo_combustible')->paginate(10)->withQueryString();

        return Inertia::render('TiposCombustible/Index', [
            // Inertia::scroll() no cambia la forma del paginador (sigue
            // trayendo data/total/from/to/links tal cual): sólo agrega la
            // metadata de merge que usa <InfiniteScroll> en el listado de
            // tarjetas (mobile). Ver .ai/rules/pages.md.
            'tipos' => Inertia::scroll($tipos),
            'filters' => $request->only(['tipo_combustible', 'estado_tipo_combustible']),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('TiposCombustible/Create');
    }

    public function store(TipoCombustibleRequest $request): RedirectResponse
    {
        TipoCombustible::create($request->validated());

        return redirect()->route('tipos-combustible.index')
            ->with('success', 'Tipo de combustible registrado exitosamente.');
    }

    public function edit(TipoCombustible $tipoCombustible): Response
    {
        return Inertia::render('TiposCombustible/Edit', [
            'tipo' => $tipoCombustible,
        ]);
    }

    public function update(TipoCombustibleRequest $request, TipoCombustible $tipoCombustible): RedirectResponse
    {
        $tipoCombustible->update($request->validated());

        return redirect()->route('tipos-combustible.index')
            ->with('success', 'Tipo de combustible actualizado exitosamente.');
    }

    public function destroy(TipoCombustible $tipoCombustible): RedirectResponse
    {
        // Verificar si tiene vehículos asociados
        if ($tipoCombustible->vehiculos()->count() > 0) {
            return redirect()->route('tipos-combustible.index')
                ->with('error', 'No se puede eliminar: existen vehículos asignados a este tipo de combustible.');
        }

        $tipoCombustible->delete();

        return redirect()->route('tipos-combustible.index')
            ->with('success', 'Tipo de combustible eliminado exitosamente.');
    }
}
