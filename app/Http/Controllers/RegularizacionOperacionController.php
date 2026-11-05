<?php

namespace App\Http\Controllers;

use App\Actions\OperacionDiaria\HabilitarRegularizacionAction;
use App\Actions\OperacionDiaria\ListUsuariosParaRegularizacionAction;
use App\Http\Requests\HabilitacionRegularizacionRequest;
use App\Http\Requests\ModoRegularizacionRequest;
use App\Models\HabilitacionRegularizacion;
use App\Models\ParametrosEmpresa;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestión de la regularización de operaciones diarias. Sólo para los
 * encargados del .env (middleware SoloEncargadoDeRegularizacion en la ruta):
 * modo transición/estricto y habilitaciones temporales por usuario.
 */
class RegularizacionOperacionController extends Controller
{
    /** Habilitaciones vencidas o revocadas que se muestran como historial. */
    private const HISTORIAL_MAXIMO = 30;

    public function index(ListUsuariosParaRegularizacionAction $listUsuarios): Response
    {
        $estrictaDesde = ParametrosEmpresa::regularizacionEstrictaDesde();
        $relaciones = ['usuario.persona', 'otorgadaPor.persona', 'revocadaPor.persona'];

        $vigentes = HabilitacionRegularizacion::with($relaciones)
            ->whereNull('revocada_at')
            ->where('vigente_hasta', '>', now())
            ->orderBy('vigente_hasta')
            ->get();

        $historial = HabilitacionRegularizacion::with($relaciones)
            ->where(fn ($query) => $query->whereNotNull('revocada_at')->orWhere('vigente_hasta', '<=', now()))
            ->latest('id')
            ->limit(self::HISTORIAL_MAXIMO)
            ->get();

        return Inertia::render('Operacion/Regularizacion', [
            'modo' => [
                'estricto' => $estrictaDesde?->lte(today()) ?? false,
                'estricto_desde' => $estrictaDesde?->toDateString(),
                'hay_parametros' => ParametrosEmpresa::query()->exists(),
            ],
            'vigentes' => $vigentes->map(fn (HabilitacionRegularizacion $h) => $this->formatear($h))->values(),
            'historial' => $historial->map(fn (HabilitacionRegularizacion $h) => $this->formatear($h))->values(),
            'usuarios' => $listUsuarios->execute(),
            'vigencias' => HabilitacionRegularizacionRequest::VIGENCIAS,
            'maxDiasVigencia' => HabilitacionRegularizacionRequest::MAX_DIAS_VIGENCIA,
        ]);
    }

    /**
     * Activa el modo estricto hoy, lo programa para un día futuro o vuelve
     * al modo transición.
     */
    public function actualizarModo(ModoRegularizacionRequest $request): RedirectResponse
    {
        $parametros = ParametrosEmpresa::query()->first();

        if (! $parametros) {
            return back()->with('error', 'Primero registra los parámetros de la empresa: ahí se guarda el modo de la operación diaria.');
        }

        $estrictaDesde = $request->estrictaDesde();
        $parametros->update(['regularizacion_estricta_desde' => $estrictaDesde]);

        return back()->with('success', match ($request->validated('accion')) {
            'activar' => 'Modo estricto activado: desde hoy sólo se registran operaciones del día (o el turno noche de ayer).',
            'programar' => 'Modo estricto programado desde el '.$estrictaDesde->format('d/m/Y').'.',
            default => 'Modo transición: se vuelve a permitir la regularización como antes.',
        });
    }

    public function store(HabilitacionRegularizacionRequest $request, HabilitarRegularizacionAction $habilitar): RedirectResponse
    {
        $habilitacion = $habilitar->execute($request->user(), $request->validated(), $request->vigenteHasta());

        return back()->with('success', 'Se habilitó a '.$this->nombreDe($habilitacion->usuario).' hasta el '.$habilitacion->vigente_hasta->format('d/m/Y H:i').'. Ya recibió el aviso.');
    }

    /**
     * Corta una habilitación vigente antes de su vencimiento.
     */
    public function revocar(Request $request, HabilitacionRegularizacion $habilitacion): RedirectResponse
    {
        if ($habilitacion->estado !== 'VIGENTE') {
            return back()->with('error', 'La habilitación ya no está vigente.');
        }

        $habilitacion->update([
            'revocada_at' => now(),
            'id_usuario_revoca' => $request->user()->id,
        ]);

        return back()->with('success', 'Habilitación de '.$this->nombreDe($habilitacion->usuario).' revocada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formatear(HabilitacionRegularizacion $habilitacion): array
    {
        return [
            'id' => $habilitacion->id,
            'estado' => $habilitacion->estado,
            'usuario' => $this->nombreDe($habilitacion->usuario),
            'email' => $habilitacion->usuario?->email,
            'desde' => $habilitacion->fecha_desde->toDateString(),
            'hasta' => $habilitacion->fecha_hasta->toDateString(),
            'vigente_hasta' => $habilitacion->vigente_hasta->toIso8601String(),
            'motivo' => $habilitacion->motivo,
            'otorgada_por' => $this->nombreDe($habilitacion->otorgadaPor),
            'otorgada_at' => $habilitacion->created_at?->toIso8601String(),
            'revocada_at' => $habilitacion->revocada_at?->toIso8601String(),
            'revocada_por' => $this->nombreDe($habilitacion->revocadaPor),
        ];
    }

    private function nombreDe(?User $usuario): ?string
    {
        return $usuario?->nombreVisible();
    }
}
