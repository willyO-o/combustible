<?php

namespace App\Http\Controllers;

use App\Http\Requests\AsignacionRequest;
use App\Http\Requests\ConductorRequest;
use App\Models\Asignacion;
use App\Models\Conductor;
use App\Models\DocumentoConductor;
use App\Models\Persona;
use App\Models\Vehiculo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ConductorController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Conductor::query()->with('asignacionesActivas')
            ->join('persona', 'persona.id', '=', 'conductor.id'); // Cargar las asignaciones activas para cada conductor

        if ($request->filled('ci')) {
            $query->where('persona.ci', 'like', '%'.$request->ci.'%');
        }
        if ($request->filled('nombres')) {
            $query->where('persona.nombres', 'like', '%'.$request->nombres.'%');
        }
        if ($request->filled('paterno')) {
            $query->where('persona.paterno', 'like', '%'.$request->paterno.'%');
        }
        if ($request->filled('celular')) {
            $query->where('persona.celular', 'like', '%'.$request->celular.'%');
        }

        $conductores = $query
            ->orderBy('persona.id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return inertia('Conductores/Index', [
            // Inertia::scroll() no cambia la forma del paginador (sigue
            // trayendo data/total/from/to/links tal cual): sólo agrega la
            // metadata de merge que usa <InfiniteScroll> en el listado de
            // tarjetas (mobile). Ver .ai/rules/pages.md.
            'conductores' => Inertia::scroll($conductores),
            'filters' => $request->only(['ci', 'nombres', 'paterno', 'celular']),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Conductores/Form', [
            'vehiculos' => $this->vehiculosParaAsignar(),
        ]);
    }

    public function store(ConductorRequest $request): RedirectResponse
    {
        $this->autorizarAsignacionSiCorresponde($request);

        DB::transaction(function () use ($request) {
            // Los datos personales (ci, nombres, foto, etc.) viven en la
            // tabla persona; el conductor sólo agrega estado_conductor
            // sobre esa misma persona (comparten "id" como llave primaria).
            $datosPersona = $request->safe()->except([
                'estado_conductor', 'foto', 'documentos',
                'id_vehiculo', 'estado_asignacion', 'fecha_asignacion',
                'fecha_culminacion', 'detalle', 'kilometraje_inicial', 'horometro_inicial',
            ]);

            if ($request->hasFile('foto')) {
                $datosPersona['foto'] = $request->file('foto')->store('conductores', 'public');
            }

            $persona = Persona::create($datosPersona);

            $conductor = Conductor::create([
                'id' => $persona->id,
                'estado_conductor' => $request->validated('estado_conductor'),
            ]);

            $this->guardarDocumentos($conductor, $request);

            if ($request->filled('id_vehiculo')) {
                $this->registrarAsignacionVehiculo($conductor, $this->datosAsignacion($request));
            }
        });

        return redirect()->route('conductores.index')
            ->with('success', 'Conductor registrado exitosamente.');
    }

    public function show(Conductor $conductor): Response
    {
        $conductor->load([
            'persona',
            'asignacionesActivas.tipoVehiculo',
            'asignacionesActivas.tipoCombustible',
            'documentos',
        ]);

        $historialAsignaciones = $conductor->asignaciones()
            ->with(['vehiculo.tipoVehiculo', 'vehiculo.tipoCombustible'])
            ->orderByDesc('fecha_asignacion')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($asignacion) => [
                'id' => $asignacion->id,
                'estado_asignacion' => $asignacion->estado_asignacion,
                'fecha_asignacion' => $asignacion->fecha_asignacion?->format('d/m/Y'),
                'fecha_culminacion' => $asignacion->fecha_culminacion?->format('d/m/Y'),
                'detalle' => $asignacion->detalle,
                'kilometraje_inicial' => $asignacion->kilometraje_inicial,
                'horometro_inicial' => $asignacion->horometro_inicial,
                'vehiculo' => $asignacion->vehiculo ? [
                    'id' => $asignacion->vehiculo->id,
                    'codigo' => $asignacion->vehiculo->codigo,
                    'nro_placa' => $asignacion->vehiculo->nro_placa,
                    'marca' => $asignacion->vehiculo->marca,
                    'modelo' => $asignacion->vehiculo->modelo,
                    'anio' => $asignacion->vehiculo->anio,
                    'url_fotografia' => $asignacion->vehiculo->url_fotografia,
                    'tipo_vehiculo' => $asignacion->vehiculo->tipoVehiculo?->tipo_vehiculo,
                    'tipo_combustible' => $asignacion->vehiculo->tipoCombustible?->tipo_combustible,
                ] : null,
            ]);

        return Inertia::render('Conductores/Show', [
            'conductor' => $conductor,
            'historialAsignaciones' => $historialAsignaciones,
        ]);
    }

    public function edit(Conductor $conductor): Response
    {
        $conductor->load(['persona', 'documentos']);

        return Inertia::render('Conductores/Form', [
            'conductor' => $conductor,
            // Sólo informativo: la asignación de vehículo se gestiona desde el
            // listado de operarios, no desde este formulario.
            'asignacionActual' => $this->asignacionActual($conductor),
        ]);
    }

    public function update(ConductorRequest $request, Conductor $conductor): RedirectResponse
    {
        DB::transaction(function () use ($request, $conductor) {
            $persona = $conductor->persona;

            $datosPersona = $request->safe()->except(['estado_conductor', 'foto', 'documentos']);

            if ($request->hasFile('foto')) {
                if ($persona->foto) {
                    Storage::disk('public')->delete($persona->foto);
                }
                $datosPersona['foto'] = $request->file('foto')->store('conductores', 'public');
            }

            $persona->update($datosPersona);

            $conductor->update([
                'estado_conductor' => $request->validated('estado_conductor'),
            ]);

            $this->guardarDocumentos($conductor, $request);
        });

        return redirect()->route('conductores.index')
            ->with('success', 'Conductor actualizado exitosamente.');
    }

    /**
     * Crea, actualiza y elimina los documentos opcionales del conductor a
     * partir del arreglo "documentos" del formulario. Los documentos con
     * "id" se actualizan (reemplazando el archivo sólo si se sube uno
     * nuevo); los que no tienen "id" se crean; los que ya no vienen en el
     * arreglo (removidos por el usuario) se eliminan junto a su archivo.
     */
    private function guardarDocumentos(Conductor $conductor, ConductorRequest $request): void
    {
        $documentos = $request->validated('documentos') ?? [];
        $idsConservados = [];

        foreach ($documentos as $item) {
            $datos = [
                'tipo_documento' => $item['tipo_documento'],
                'numero_documento' => $item['numero_documento'] ?? null,
                'categoria' => $item['categoria'] ?? null,
                'fecha_emision' => $item['fecha_emision'] ?? null,
                'fecha_vencimiento' => $item['fecha_vencimiento'] ?? null,
                'estado_documento' => $item['estado_documento'] ?? 'VIGENTE',
                'observacion' => $item['observacion'] ?? null,
            ];

            $documento = ! empty($item['id'])
                ? $conductor->documentos()->find($item['id'])
                : null;

            if (($item['archivo'] ?? null) instanceof UploadedFile) {
                if ($documento?->archivo) {
                    Storage::disk('public')->delete($documento->archivo);
                }
                $datos['archivo'] = $item['archivo']->store('documentos-conductores', 'public');
            }

            $documento = $documento
                ? tap($documento)->update($datos)
                : $conductor->documentos()->create($datos);

            $idsConservados[] = $documento->id;
        }

        $conductor->documentos()
            ->whereNotIn('id', $idsConservados)
            ->get()
            ->each(function (DocumentoConductor $documento) {
                if ($documento->archivo) {
                    Storage::disk('public')->delete($documento->archivo);
                }
                $documento->delete();
            });
    }

    public function destroy(Conductor $conductor): RedirectResponse
    {
        // La foto pertenece a la persona (no al conductor) y la persona no
        // se elimina aquí: sólo se revoca el rol de conductor.
        $conductor->delete();

        return redirect()->route('conductores.index')
            ->with('success', 'Conductor eliminado exitosamente.');
    }

    /**
     * Asigna (o reasigna) un vehículo a un conductor. La asignación activa
     * anterior de este conductor (si la tiene) queda REASIGNADO con
     * fecha_culminacion=ahora; nunca conviven dos asignaciones activas para
     * el mismo conductor. Si el vehículo seleccionado ya estaba asignado
     * activamente a otro conductor, esa asignación también queda REASIGNADO
     * con fecha_culminacion=ahora, liberando al conductor anterior. El
     * usuario que realiza la asignación se registra automáticamente desde
     * el servidor.
     */
    public function asignarVehiculo(AsignacionRequest $request, Conductor $conductor): RedirectResponse
    {
        DB::transaction(fn () => $this->registrarAsignacionVehiculo($conductor, $request->validated()));

        return redirect()->route('conductores.index')
            ->with('success', 'Vehículo asignado exitosamente.');
    }

    /**
     * Crea la asignación de un vehículo a un conductor cerrando (REASIGNADO)
     * cualquier asignación vigente previa del propio conductor y liberando al
     * conductor que tuviera ese vehículo. Mantiene la invariante de una sola
     * asignación vigente por conductor y por vehículo. Reasignar el mismo
     * vehículo con el mismo tipo de asignación no genera cambios.
     *
     * Compartido por el modal de reasignación (asignarVehiculo) y por el
     * formulario de alta/edición del operario (store/update). Los datos ya
     * vienen validados; sólo id_vehiculo es obligatorio.
     *
     * @param  array<string, mixed>  $datos
     */
    private function registrarAsignacionVehiculo(Conductor $conductor, array $datos): void
    {
        $condicionVigente = function ($query) {
            $query->whereNull('fecha_culminacion')
                ->orWhere('fecha_culminacion', '>', now());
        };

        $estadoAsignacion = $datos['estado_asignacion'] ?? 'ACTIVO';

        $asignacionVigente = Asignacion::where('id_conductor', $conductor->id)
            ->whereIn('estado_asignacion', ['ACTIVO', 'PROVISIONAL'])
            ->where($condicionVigente)
            ->latest('fecha_asignacion')
            ->first();

        if (
            $asignacionVigente
            && (int) $asignacionVigente->id_vehiculo === (int) $datos['id_vehiculo']
            && $asignacionVigente->estado_asignacion === $estadoAsignacion
        ) {
            return;
        }

        // Libera al conductor que tuviera este vehículo asignado actualmente.
        Asignacion::where('id_vehiculo', $datos['id_vehiculo'])
            ->where('id_conductor', '!=', $conductor->id)
            ->whereIn('estado_asignacion', ['ACTIVO', 'PROVISIONAL'])
            ->where($condicionVigente)
            ->update([
                'estado_asignacion' => 'REASIGNADO',
                'fecha_culminacion' => now(),
            ]);

        // Cierra la asignación vigente anterior del propio conductor.
        Asignacion::where('id_conductor', $conductor->id)
            ->whereIn('estado_asignacion', ['ACTIVO', 'PROVISIONAL'])
            ->where($condicionVigente)
            ->update([
                'estado_asignacion' => 'REASIGNADO',
                'fecha_culminacion' => now(),
            ]);

        $esProvisional = $estadoAsignacion === 'PROVISIONAL';

        Asignacion::create([
            'id_vehiculo' => $datos['id_vehiculo'],
            'id_conductor' => $conductor->id,
            'fecha_asignacion' => $datos['fecha_asignacion'] ?? now(),
            'fecha_culminacion' => $esProvisional ? ($datos['fecha_culminacion'] ?? null) : null,
            'estado_asignacion' => $estadoAsignacion,
            'detalle' => $datos['detalle'] ?? null,
            'kilometraje_inicial' => $datos['kilometraje_inicial'] ?? null,
            'horometro_inicial' => $datos['horometro_inicial'] ?? null,
            'id_usuario' => auth()->id(),
        ]);
    }

    /**
     * Normaliza los datos de asignación que llegan por el formulario del
     * operario para pasarlos a registrarAsignacionVehiculo().
     *
     * @return array<string, mixed>
     */
    private function datosAsignacion(ConductorRequest $request): array
    {
        return [
            'id_vehiculo' => $request->validated('id_vehiculo'),
            'estado_asignacion' => $request->validated('estado_asignacion') ?: 'ACTIVO',
            'fecha_asignacion' => $request->validated('fecha_asignacion'),
            'fecha_culminacion' => $request->validated('fecha_culminacion'),
            'detalle' => $request->validated('detalle'),
            'kilometraje_inicial' => $request->validated('kilometraje_inicial'),
            'horometro_inicial' => $request->validated('horometro_inicial'),
        ];
    }

    /**
     * Sólo un jefe de área o administrador puede asignar vehículos (mismo
     * criterio que AsignacionRequest). Si el formulario del operario no
     * incluye una asignación, cualquiera con permiso de alta/edición pasa.
     */
    private function autorizarAsignacionSiCorresponde(ConductorRequest $request): void
    {
        if (! $request->filled('id_vehiculo')) {
            return;
        }

        abort_unless(
            $request->user()->hasAnyRole(['super-admin', 'administrador', 'jefe-area']),
            403,
            'Sólo un jefe de área o administrador puede asignar vehículos.',
        );
    }

    /**
     * Vehículos activos para el selector de asignación del formulario, con el
     * nombre del conductor que los tiene asignado actualmente (o null), para
     * mostrar la etiqueta "Asignado / Sin asignación" en el buscador.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function vehiculosParaAsignar()
    {
        return Vehiculo::query()
            ->where('estado_vehiculo', 'ACTIVO')
            ->with('conductorAsignado.persona:id,nombres,paterno')
            ->orderBy('codigo')
            ->get()
            ->map(fn (Vehiculo $vehiculo) => [
                'id' => $vehiculo->id,
                'codigo' => $vehiculo->codigo,
                'nro_placa' => $vehiculo->nro_placa,
                'marca' => $vehiculo->marca,
                'anio' => $vehiculo->anio,
                'tipo_medicion' => $vehiculo->tipo_medicion,
                'conductor_asignado' => $vehiculo->conductorAsignado?->persona
                    ? trim($vehiculo->conductorAsignado->persona->nombres.' '.$vehiculo->conductorAsignado->persona->paterno)
                    : null,
            ]);
    }

    /**
     * Asignación vigente (ACTIVO/PROVISIONAL) del conductor para mostrarla en
     * el formulario de edición, o null si no tiene vehículo asignado.
     *
     * @return array<string, mixed>|null
     */
    private function asignacionActual(Conductor $conductor): ?array
    {
        $asignacion = $conductor->asignaciones()
            ->with('vehiculo:id,codigo,nro_placa,marca,anio')
            ->whereIn('estado_asignacion', ['ACTIVO', 'PROVISIONAL'])
            ->where(function ($query) {
                $query->whereNull('fecha_culminacion')
                    ->orWhere('fecha_culminacion', '>', now());
            })
            ->latest('fecha_asignacion')
            ->first();

        if (! $asignacion) {
            return null;
        }

        return [
            'estado_asignacion' => $asignacion->estado_asignacion,
            'fecha_asignacion' => $asignacion->fecha_asignacion?->format('d/m/Y'),
            'vehiculo' => $asignacion->vehiculo ? [
                'codigo' => $asignacion->vehiculo->codigo,
                'nro_placa' => $asignacion->vehiculo->nro_placa,
                'marca' => $asignacion->vehiculo->marca,
                'anio' => $asignacion->vehiculo->anio,
            ] : null,
        ];
    }

    /**
     * Finaliza manualmente una asignación activa/provisional, sin
     * reemplazarla de inmediato (el conductor queda sin vehículo).
     */
    public function finalizarAsignacion(Request $request, Conductor $conductor, Asignacion $asignacion): RedirectResponse
    {
        if (! $request->user()->hasAnyRole(['super-admin', 'administrador', 'jefe-area'])) {
            abort(403, 'Sólo un jefe de área o administrador puede finalizar asignaciones.');
        }

        if ((int) $asignacion->id_conductor !== $conductor->id) {
            abort(404);
        }

        $asignacion->update([
            'estado_asignacion' => 'INACTIVO',
            'fecha_culminacion' => now(),
        ]);

        return redirect()->route('conductores.index')
            ->with('success', 'Asignación finalizada exitosamente.');
    }
}
