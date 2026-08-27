<?php

namespace App\Http\Controllers;

use App\Http\Requests\RepuestoRequest;
use App\Models\Repuesto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RepuestoController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Repuesto::query();

        if ($request->filled('nombre_repuesto')) {
            $query->where('nombre_repuesto', 'like', '%'.$request->nombre_repuesto.'%');
        }
        if ($request->filled('codigo_repuesto')) {
            $query->where('codigo_repuesto', 'like', '%'.$request->codigo_repuesto.'%');
        }
        if ($request->filled('estado_repuesto')) {
            $query->where('estado_repuesto', $request->estado_repuesto);
        }

        $repuestos = $query->orderBy('nombre_repuesto')->paginate(10)->withQueryString();

        return Inertia::render('Repuestos/Index', [
            // Inertia::scroll() no cambia la forma del paginador (sigue
            // trayendo data/total/from/to/links tal cual): sólo agrega la
            // metadata de merge que usa <InfiniteScroll> en el listado de
            // tarjetas (mobile). Ver .ai/rules/pages.md.
            'repuestos' => Inertia::scroll($repuestos),
            'filters' => $request->only(['nombre_repuesto', 'codigo_repuesto', 'estado_repuesto']),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Repuestos/Create');
    }

    public function store(RepuestoRequest $request): RedirectResponse
    {
        Repuesto::create($request->validated());

        return redirect()->route('repuestos.index')
            ->with('success', 'Repuesto registrado exitosamente.');
    }

    public function edit(Repuesto $repuesto): Response
    {
        return Inertia::render('Repuestos/Edit', [
            'repuesto' => $repuesto,
        ]);
    }

    public function update(RepuestoRequest $request, Repuesto $repuesto): RedirectResponse
    {
        $repuesto->update($request->validated());

        return redirect()->route('repuestos.index')
            ->with('success', 'Repuesto actualizado exitosamente.');
    }

    public function destroy(Repuesto $repuesto): RedirectResponse
    {
        if ($repuesto->detallesMantenimiento()->count() > 0) {
            return redirect()->route('repuestos.index')
                ->with('error', 'No se puede eliminar: el repuesto está registrado en detalles de órdenes de trabajo.');
        }

        $repuesto->delete();

        return redirect()->route('repuestos.index')
            ->with('success', 'Repuesto eliminado exitosamente.');
    }
}
