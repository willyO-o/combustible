<?php

namespace App\Http\Controllers;

use App\Actions\Personas\CreatePersonaAction;
use App\Actions\Personas\UpdatePersonaAction;
use App\Http\Requests\PersonaRequest;
use App\Models\Area;
use App\Models\Persona;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PersonaController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Persona::with(['user', 'conductor', 'encargadoAreas']);

        if ($request->filled('ci')) {
            $query->where('ci', 'like', '%'.$request->ci.'%');
        }
        if ($request->filled('nombres')) {
            $query->where('nombres', 'like', '%'.$request->nombres.'%');
        }
        if ($request->filled('paterno')) {
            $query->where('paterno', 'like', '%'.$request->paterno.'%');
        }
        if ($request->filled('celular')) {
            $query->where('celular', 'like', '%'.$request->celular.'%');
        }

        $personas = $query
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Personas/Index', [
            'personas' => $personas,
            'filters' => $request->only(['ci', 'nombres', 'paterno', 'celular']),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Personas/Create', [
            'areas' => Area::where('estado_area', 'ACTIVO')->orderBy('nombre_area')->get(['id', 'nombre_area']),
        ]);
    }

    public function store(PersonaRequest $request, CreatePersonaAction $action): RedirectResponse
    {
        if (! $request->user()->hasAnyRole('super-admin', 'administrador')) {
            abort(403);
        }

        $data = $request->validated();

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('personas', 'public');
        }

        $action->execute($data);

        return redirect()->route('personas.index')
            ->with('success', 'Persona registrada exitosamente.');
    }

    public function edit(Persona $persona): Response
    {
        $persona->load(['user', 'conductor.asignacionesActivas', 'encargadoAreas']);

        return Inertia::render('Personas/Edit', [
            'persona' => $persona,
            'areas' => Area::where('estado_area', 'ACTIVO')->orderBy('nombre_area')->get(['id', 'nombre_area']),
            'vehiculoActual' => $persona->conductor?->asignacionesActivas->first(),
            'areaActual' => $persona->encargadoAreas->first(),
        ]);
    }

    public function update(PersonaRequest $request, Persona $persona, UpdatePersonaAction $action): RedirectResponse
    {
        if (! $request->user()->hasAnyRole('super-admin', 'administrador')) {
            abort(403);
        }

        $data = $request->validated();

        if ($request->hasFile('foto')) {
            if ($persona->foto) {
                Storage::disk('public')->delete($persona->foto);
            }
            $data['foto'] = $request->file('foto')->store('personas', 'public');
        } else {
            unset($data['foto']);
        }

        $action->execute($persona, $data);

        return redirect()->route('personas.index')
            ->with('success', 'Persona actualizada exitosamente.');
    }

    public function destroy(Request $request, Persona $persona): RedirectResponse
    {
        if (! $request->user()->hasAnyRole('super-admin', 'administrador')) {
            abort(403);
        }

        if ($persona->foto) {
            Storage::disk('public')->delete($persona->foto);
        }

        $persona->delete();

        return redirect()->route('personas.index')
            ->with('success', 'Persona eliminada exitosamente.');
    }
}
