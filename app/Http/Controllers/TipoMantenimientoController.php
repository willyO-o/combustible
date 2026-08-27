<?php

namespace App\Http\Controllers;

use App\Http\Requests\TipoMantenimientoRequest;
use App\Models\TipoMantenimiento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TipoMantenimientoController extends Controller
{
    public function index(Request $request): Response
    {
        $query = TipoMantenimiento::query();

        if ($request->filled('tipo_mantenimiento')) {
            $query->where('tipo_mantenimiento', 'like', '%'.$request->tipo_mantenimiento.'%');
        }
        if ($request->filled('estado_tipo_mantenimiento')) {
            $query->where('estado_tipo_mantenimiento', $request->estado_tipo_mantenimiento);
        }

        $tipos = $query->orderBy('tipo_mantenimiento')->paginate(10)->withQueryString();

        return Inertia::render('TiposMantenimiento/Index', [
            // Inertia::scroll() no cambia la forma del paginador (sigue
            // trayendo data/total/from/to/links tal cual): sólo agrega la
            // metadata de merge que usa <InfiniteScroll> en el listado de
            // tarjetas (mobile). Ver .ai/rules/pages.md.
            'tipos' => Inertia::scroll($tipos),
            'filters' => $request->only(['tipo_mantenimiento', 'estado_tipo_mantenimiento']),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('TiposMantenimiento/Create');
    }

    public function store(TipoMantenimientoRequest $request): RedirectResponse
    {
        TipoMantenimiento::create($request->validated());

        return redirect()->route('tipos-mantenimiento.index')
            ->with('success', 'Tipo de mantenimiento registrado exitosamente.');
    }

    public function edit(TipoMantenimiento $tipoMantenimiento): Response
    {
        return Inertia::render('TiposMantenimiento/Edit', [
            'tipo' => $tipoMantenimiento,
        ]);
    }

    public function update(TipoMantenimientoRequest $request, TipoMantenimiento $tipoMantenimiento): RedirectResponse
    {
        $tipoMantenimiento->update($request->validated());

        return redirect()->route('tipos-mantenimiento.index')
            ->with('success', 'Tipo de mantenimiento actualizado exitosamente.');
    }

    public function destroy(TipoMantenimiento $tipoMantenimiento): RedirectResponse
    {
        if ($tipoMantenimiento->mantenimientos()->count() > 0) {
            return redirect()->route('tipos-mantenimiento.index')
                ->with('error', 'No se puede eliminar: existen mantenimientos asignados a este tipo.');
        }

        $tipoMantenimiento->delete();

        return redirect()->route('tipos-mantenimiento.index')
            ->with('success', 'Tipo de mantenimiento eliminado exitosamente.');
    }
}
