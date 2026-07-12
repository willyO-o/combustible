<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConductorRequest;
use App\Models\Conductor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ConductorController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Conductor::query()->with('asignacioneActivas'); // Cargar las asignaciones activas para cada conductor

        if ($request->filled('ci')) {
            $query->where('ci', 'like', '%' . $request->ci . '%');
        }
        if ($request->filled('nombres')) {
            $query->where('nombres', 'like', '%' . $request->nombres . '%');
        }
        if ($request->filled('paterno')) {
            $query->where('paterno', 'like', '%' . $request->paterno . '%');
        }
        if ($request->filled('celular')) {
            $query->where('celular', 'like', '%' . $request->celular . '%');
        }

        $conductores = $query
            ->orderBy('nombres')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Conductores/Index', [
            'conductores' => $conductores,
            'filters'     => $request->only(['ci', 'nombres', 'paterno', 'celular']),
            'flash'       => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Conductores/Create');
    }

    public function store(ConductorRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('conductores', 'public');
        }

        Conductor::create($data);

        return redirect()->route('conductores.index')
            ->with('success', 'Conductor registrado exitosamente.');
    }

    public function edit(Conductor $conductor): Response
    {
        return Inertia::render('Conductores/Edit', [
            'conductor' => $conductor,
        ]);
    }

    public function update(ConductorRequest $request, Conductor $conductor): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('foto')) {
            if ($conductor->foto) {
                Storage::disk('public')->delete($conductor->foto);
            }
            $data['foto'] = $request->file('foto')->store('conductores', 'public');
        } else {
            unset($data['foto']);
        }

        $conductor->update($data);

        return redirect()->route('conductores.index')
            ->with('success', 'Conductor actualizado exitosamente.');
    }

    public function destroy(Conductor $conductor): RedirectResponse
    {
        if ($conductor->foto) {
            Storage::disk('public')->delete($conductor->foto);
        }

        $conductor->delete();

        return redirect()->route('conductores.index')
            ->with('success', 'Conductor eliminado exitosamente.');
    }
}
