<?php

namespace App\Http\Controllers;

use App\Http\Requests\GrifoRequest;
use App\Models\Grifo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GrifoController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Grifo::query();

        if ($request->filled('razon_social')) {
            $query->where('razon_social', 'like', '%' . $request->razon_social . '%');
        }
        if ($request->filled('nit')) {
            $query->where('nit', 'like', '%' . $request->nit . '%');
        }
        if ($request->filled('ciudad')) {
            $query->where('ciudad', 'like', '%' . $request->ciudad . '%');
        }
        if ($request->filled('estado_grifo')) {
            $query->where('estado_grifo', $request->estado_grifo);
        }

        $grifos = $query
            ->orderBy('razon_social')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Grifos/Index', [
            'grifos'  => $grifos,
            'filters' => $request->only(['razon_social', 'nit', 'ciudad', 'estado_grifo']),
            'flash'   => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Grifos/Create');
    }

    public function store(GrifoRequest $request): RedirectResponse
    {
        Grifo::create($request->validated());

        return redirect()->route('grifos.index')
            ->with('success', 'Grifo registrado exitosamente.');
    }

    public function edit(Grifo $grifo): Response
    {
        return Inertia::render('Grifos/Edit', [
            'grifo' => $grifo,
        ]);
    }

    public function update(GrifoRequest $request, Grifo $grifo): RedirectResponse
    {
        $grifo->update($request->validated());

        return redirect()->route('grifos.index')
            ->with('success', 'Grifo actualizado exitosamente.');
    }

    public function destroy(Grifo $grifo): RedirectResponse
    {
        $grifo->delete();

        return redirect()->route('grifos.index')
            ->with('success', 'Grifo eliminado exitosamente.');
    }
}
