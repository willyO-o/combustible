<?php

namespace App\Http\Controllers;

use App\Models\CargaCombustible;
use App\Models\Conductor;
use App\Models\OperacionDiaria;
use App\Models\OrdenTrabajo;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Cada widget del dashboard (tarjetas y gráficos) está detrás de su propio
     * permiso `dashboard.*`. Sólo se calcula y se envía la métrica si el usuario
     * tiene el permiso; en el frontend además se oculta con v-can.
     *
     * Alcance de los datos según el rol:
     * - jefe-area: cada métrica se acota a los vehículos de sus áreas.
     * - técnico de mantenimiento: sólo sus órdenes de trabajo asignadas.
     * - conductor: sólo sus operaciones diarias.
     * - administrador / super-admin: todo.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $idVehiculos = $this->idsVehiculosEnAlcance($user);

        $payload = [];

        if ($user->can('dashboard.tarjeta-cargas.ver')) {
            $payload['cargas'] = $this->metricaCargas($idVehiculos);
        }

        if ($user->can('dashboard.tarjeta-vales.ver')) {
            $payload['vales'] = $this->metricaVales($idVehiculos);
        }

        if ($user->can('dashboard.tarjeta-vehiculos.ver')) {
            $payload['vehiculos'] = $this->metricaVehiculos($idVehiculos);
        }

        if ($user->can('dashboard.tarjeta-conductores.ver')) {
            $payload['conductores'] = $this->metricaConductores($idVehiculos);
        }

        if ($user->can('dashboard.grafico-combustible.ver')) {
            $payload['reporteMes'] = CargaCombustible::reporteCargaCombustibleMes(null, $idVehiculos?->all());
        }

        if ($user->can('dashboard.grafico-ordenes.ver')) {
            $payload['ordenesPorEstado'] = $this->metricaOrdenesPorEstado($user);
        }

        if ($user->can('dashboard.grafico-horas.ver')) {
            $payload['horasTrabajadas'] = $this->metricaHorasTrabajadas($user);
        }

        return Inertia::render('Dashboard', $payload);
    }

    /**
     * IDs de los vehículos dentro del alcance del usuario:
     * - null  => sin filtro (administrador / super-admin ven todo).
     * - Collection<int> => sólo los vehículos asignados a las áreas que el
     *   jefe de área tiene a cargo.
     */
    private function idsVehiculosEnAlcance(User $user): ?Collection
    {
        if (! $user->hasRole('jefe-area') || $user->hasAnyRole(['administrador', 'super-admin'])) {
            return null;
        }

        $idAreas = $user->persona?->encargadoAreas()->pluck('id_area') ?? collect();

        return Vehiculo::whereHas(
            'areasAsignadas',
            fn ($query) => $query->whereIn('vehiculo_area.id_area', $idAreas)
        )->pluck('id');
    }

    /**
     * @return array{total: int, porcentaje: string}
     */
    private function metricaCargas(?Collection $idVehiculos): array
    {
        $base = fn () => CargaCombustible::query()
            ->when($idVehiculos !== null, fn ($query) => $query->whereIn('id_vehiculo', $idVehiculos));

        $total = $base()
            ->whereMonth('fecha_carga', now()->month)
            ->whereYear('fecha_carga', now()->year)
            ->count();

        $mesAnterior = $base()
            ->whereMonth('fecha_carga', now()->subMonth()->month)
            ->whereYear('fecha_carga', now()->subMonth()->year)
            ->count();

        return [
            'total' => $total,
            'porcentaje' => $this->porcentaje($mesAnterior, $total),
        ];
    }

    /**
     * @return array{total: int, porcentaje: string}
     */
    private function metricaVales(?Collection $idVehiculos): array
    {
        $base = fn () => CargaCombustible::query()
            ->whereNotNull('id_vale')
            ->when($idVehiculos !== null, fn ($query) => $query->whereIn('id_vehiculo', $idVehiculos));

        $total = $base()->count();

        $mesAnterior = $base()
            ->whereMonth('fecha_carga', now()->subMonth()->month)
            ->whereYear('fecha_carga', now()->subMonth()->year)
            ->count();

        return [
            'total' => $total,
            'porcentaje' => $this->porcentaje($mesAnterior, $total),
        ];
    }

    /**
     * @return array{total: int, porcentaje: string}
     */
    private function metricaVehiculos(?Collection $idVehiculos): array
    {
        $base = fn () => Vehiculo::query()
            ->when($idVehiculos !== null, fn ($query) => $query->whereIn('id', $idVehiculos));

        $total = $base()->count();
        $nuevos = $base()->where('created_at', '>=', now()->subMonth())->count();

        return [
            'total' => $total,
            'porcentaje' => $this->porcentaje($nuevos, $total),
        ];
    }

    /**
     * @return array{total: int, porcentaje: string}
     */
    private function metricaConductores(?Collection $idVehiculos): array
    {
        $base = fn () => Conductor::query()
            ->when($idVehiculos !== null, fn ($query) => $query->whereHas(
                'asignaciones',
                fn ($asignacion) => $asignacion
                    ->whereIn('id_vehiculo', $idVehiculos)
                    ->whereIn('estado_asignacion', ['ACTIVO', 'PROVISIONAL'])
            ));

        $total = $base()->count();
        $nuevos = $base()->where('created_at', '>=', now()->subMonth())->count();

        return [
            'total' => $total,
            'porcentaje' => $this->porcentaje($nuevos, $total),
        ];
    }

    /**
     * Órdenes de trabajo agrupadas por estado, para el gráfico de torta.
     * El COUNT/GROUP BY se resuelve en la base de datos; en PHP sólo se
     * ordena por el flujo del estado y se descartan los estados sin registros.
     *
     * Un técnico de mantenimiento "puro" (sin rol de gestión) sólo ve sus
     * propias órdenes asignadas, igual que en OrdenTrabajoController.
     *
     * @return array{labels: array<int, string>, series: array<int, int>}
     */
    private function metricaOrdenesPorEstado(User $user): array
    {
        $soloTecnico = $user->hasRole('tecnico-mantenimiento')
            && ! $user->hasAnyRole(['super-admin', 'administrador', 'jefe-area']);

        $conteo = OrdenTrabajo::query()
            ->when($soloTecnico, fn ($query) => $query->where('id_usuario_ejecuta', $user->id))
            ->groupBy('estado_orden')
            ->selectRaw('estado_orden, COUNT(*) as total')
            ->pluck('total', 'estado_orden');

        $etiquetas = [
            'PENDIENTE' => 'Pendiente',
            'EN_EJECUCION' => 'En ejecución',
            'CULMINADO' => 'Culminado',
            'VERIFICADO' => 'Verificado',
            'CANCELADO' => 'Cancelado',
        ];

        $labels = [];
        $series = [];

        foreach ($etiquetas as $estado => $etiqueta) {
            $total = (int) ($conteo[$estado] ?? 0);

            if ($total === 0) {
                continue;
            }

            $labels[] = $etiqueta;
            $series[] = $total;
        }

        return ['labels' => $labels, 'series' => $series];
    }

    /**
     * Horas trabajadas (operacion_diaria.horas_trabajadas) agregadas por día,
     * para el gráfico de barras del rol conductor. Dos vistas: últimos 7 días y
     * últimas 6 semanas (lunes a domingo).
     *
     * El SUM se resuelve en la base de datos agrupando por DATE() (portable
     * SQLite/MySQL); el reparto de esos totales diarios en semanas se hace en
     * PHP porque la función de semana no es portable entre motores.
     *
     * Un conductor "puro" (sin rol de gestión) sólo ve sus propias operaciones
     * (id_conductor = su persona, mismo criterio que ListOperacionesDiariasAction).
     *
     * @return array{
     *     dia: array{labels: array<int, string>, series: array<int, float>},
     *     semana: array{labels: array<int, string>, series: array<int, float>}
     * }
     */
    private function metricaHorasTrabajadas(User $user): array
    {
        $soloConductor = $user->hasRole('conductor')
            && ! $user->hasAnyRole(['super-admin', 'administrador', 'jefe-area']);

        $porDia = OperacionDiaria::query()
            ->when($soloConductor, fn ($query) => $query->where('id_conductor', $user->id_persona))
            ->whereNotNull('horas_trabajadas')
            ->where('fecha_inicio', '>=', now()->startOfDay()->subDays(41))
            ->groupByRaw('DATE(fecha_inicio)')
            ->selectRaw('DATE(fecha_inicio) as dia, SUM(horas_trabajadas) as horas')
            ->pluck('horas', 'dia');

        $horasDe = fn (string $ymd): float => round((float) ($porDia[$ymd] ?? 0), 2);

        $dias = collect(range(6, 0))->map(function (int $atras) use ($horasDe) {
            $fecha = now()->startOfDay()->subDays($atras);

            return ['label' => $fecha->format('d/m'), 'horas' => $horasDe($fecha->format('Y-m-d'))];
        });

        $semanas = collect(range(5, 0))->map(function (int $atras) use ($horasDe) {
            $lunes = now()->startOfWeek()->subWeeks($atras);

            $horas = collect(range(0, 6))
                ->sum(fn (int $dia) => $horasDe($lunes->copy()->addDays($dia)->format('Y-m-d')));

            return ['label' => 'Sem. '.$lunes->format('d/m'), 'horas' => round($horas, 2)];
        });

        return [
            'dia' => ['labels' => $dias->pluck('label')->all(), 'series' => $dias->pluck('horas')->all()],
            'semana' => ['labels' => $semanas->pluck('label')->all(), 'series' => $semanas->pluck('horas')->all()],
        ];
    }

    /**
     * Porcentaje relativo con formato "+N%" (misma forma que espera CardAnalitic),
     * a prueba de división por cero.
     */
    private function porcentaje(int $parte, int $total): string
    {
        if ($total <= 0) {
            return '+0%';
        }

        return '+'.round($parte / $total * 100, 1).'%';
    }
}
