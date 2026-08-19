<?php

namespace App\Http\Controllers;

use App\Http\Requests\ParametrosEmpresaRequest;
use App\Models\ParametrosEmpresa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ParametrosEmpresaController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('ParametrosEmpresa/Edit', [
            'parametrosEmpresa' => ParametrosEmpresa::first(),
        ]);
    }

    public function update(ParametrosEmpresaRequest $request): RedirectResponse
    {
        if (! $request->user()->hasAnyRole(['super-admin', 'administrador'])) {
            abort(403);
        }

        $parametrosEmpresa = ParametrosEmpresa::first();
        $data = $request->validated();

        if ($request->hasFile('logo_empresa')) {
            if ($parametrosEmpresa?->logo_empresa) {
                Storage::disk('public')->delete($parametrosEmpresa->logo_empresa);
            }
            $data['logo_empresa'] = $request->file('logo_empresa')->store('parametros-empresa', 'public');
        } else {
            unset($data['logo_empresa']);
        }

        if ($parametrosEmpresa) {
            $parametrosEmpresa->update($data);
        } else {
            ParametrosEmpresa::create($data);
        }

        return redirect()->route('parametros-empresa.edit')
            ->with('success', 'Parámetros de la empresa actualizados exitosamente.');
    }
}
