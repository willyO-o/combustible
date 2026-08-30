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
    /**
     * Ámbitos válidos. El listado (index) y el formulario (create) comparten
     * una única vista para los dos "tableros": el ámbito viaja como query
     * param y sólo cambia qué columnas/campos se muestran.
     */
    private const AMBITOS = ['taller', 'operacion_diaria'];

    public function index(Request $request): Response
    {
        $ambito = $this->ambitoDesde($request);

        $query = TipoMantenimiento::where('ambito', $ambito);

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
            'ambito' => $ambito,
            'filters' => $request->only(['tipo_mantenimiento', 'estado_tipo_mantenimiento']),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('TiposMantenimiento/Create', [
            'ambito' => $this->ambitoDesde($request),
        ]);
    }

    public function store(TipoMantenimientoRequest $request): RedirectResponse
    {
        $tipo = TipoMantenimiento::create($this->normalizarCampos($request->validated()));

        return redirect()->route('tipos-mantenimiento.index', ['ambito' => $tipo->ambito])
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
        $tipoMantenimiento->update($this->normalizarCampos($request->validated()));

        return redirect()->route('tipos-mantenimiento.index', ['ambito' => $tipoMantenimiento->ambito])
            ->with('success', 'Tipo de mantenimiento actualizado exitosamente.');
    }

    public function destroy(TipoMantenimiento $tipoMantenimiento): RedirectResponse
    {
        if ($tipoMantenimiento->mantenimientos()->count() > 0) {
            return redirect()->route('tipos-mantenimiento.index', ['ambito' => $tipoMantenimiento->ambito])
                ->with('error', 'No se puede eliminar: existen mantenimientos asignados a este tipo.');
        }

        $ambito = $tipoMantenimiento->ambito;
        $tipoMantenimiento->delete();

        return redirect()->route('tipos-mantenimiento.index', ['ambito' => $ambito])
            ->with('success', 'Tipo de mantenimiento eliminado exitosamente.');
    }

    private function ambitoDesde(Request $request): string
    {
        return in_array($request->input('ambito'), self::AMBITOS, true)
            ? $request->input('ambito')
            : 'taller';
    }

    /**
     * tipo_valor y unidad_medida sólo tienen sentido en el ámbito operación
     * diaria; unidad_medida además sólo cuando el valor es una cantidad. En
     * cualquier otro caso se guardan como null para no dejar datos huérfanos.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizarCampos(array $data): array
    {
        if (($data['ambito'] ?? 'taller') !== 'operacion_diaria') {
            $data['tipo_valor'] = null;
            $data['unidad_medida'] = null;
        } elseif (($data['tipo_valor'] ?? null) !== 'cantidad') {
            $data['unidad_medida'] = null;
        }

        return $data;
    }
}
