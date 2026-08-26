<?php

namespace Database\Seeders;

use App\Models\DetalleMantenimiento;
use App\Models\OrdenTrabajo;
use App\Models\Repuesto;
use App\Models\SolicitudMantenimiento;
use App\Models\TipoMantenimiento;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Datos de prueba para el reporte "SOLICITUD DE MANTENIMIENTO EQUIPO"
 * (Reportes::generarSolicitudMantenimiento) — cubre los casos que la
 * plantilla necesita ejercitar: sin orden de trabajo todavía, orden emitida
 * sin detalle, pocas líneas de detalle con textos largos, más líneas de
 * detalle que las que entran en la tabla (trunca a 10) y la tabla llena
 * justo al límite. No se registra en DatabaseSeeder::run() porque es data
 * transaccional de prueba, no un catálogo — ejecutar manualmente con:
 *   php artisan db:seed --class=SolicitudMantenimientoDemoSeeder
 *
 * Requiere que ya existan los seeders base (roles/usuarios, vehículos,
 * repuestos, tipos de mantenimiento, parámetros de la empresa).
 */
class SolicitudMantenimientoDemoSeeder extends Seeder
{
    public function run(): void
    {
        $conductores = User::role('conductor')->where('estado_usuario', 'ACTIVO')->whereNotNull('id_persona')->get();
        $jefesArea = User::role('jefe-area')->where('estado_usuario', 'ACTIVO')->get();
        $tecnicos = User::role('tecnico-mantenimiento')->where('estado_usuario', 'ACTIVO')->get();
        $vehiculoKm = Vehiculo::where('tipo_medicion', 'kilometraje')->where('estado_vehiculo', 'ACTIVO')->inRandomOrder()->first();
        $vehiculoHr = Vehiculo::where('tipo_medicion', 'horometro')->where('estado_vehiculo', 'ACTIVO')->inRandomOrder()->first();
        $repuestos = Repuesto::where('estado_repuesto', 'ACTIVO')->get();
        $tiposMantenimiento = TipoMantenimiento::where('estado_tipo_mantenimiento', 'ACTIVO')->get();

        if ($conductores->count() < 3 || $jefesArea->isEmpty() || $tecnicos->isEmpty()
            || ! $vehiculoKm || ! $vehiculoHr || $repuestos->isEmpty() || $tiposMantenimiento->isEmpty()) {
            $this->command->warn('SolicitudMantenimientoDemoSeeder: faltan datos base (usuarios/vehículos/repuestos/tipos de mantenimiento). Ejecuta primero los seeders principales.');

            return;
        }

        $jefeArea = $jefesArea->first();
        $tecnico1 = $tecnicos->first();
        $tecnico2 = $tecnicos->count() > 1 ? $tecnicos[1] : $tecnicos->first();

        // ── Caso 1: recién registrada, aún sin orden de trabajo (Paso 1) ──
        // La sección "TRABAJOS REALIZADOS" debe verse vacía y las firmas de
        // "EJECUTOR DE TRABAJO"/"Vo. Bo." no deben imprimir ningún nombre.
        $this->crearSolicitud($conductores[0], $vehiculoKm, 'PREVENTIVO', 'Ruido extraño en el motor al acelerar.', null);

        // ── Caso 2: orden emitida, todavía sin ningún ítem de detalle ──
        // La tabla debe verse vacía (sólo encabezado + filas en blanco) pero
        // el ejecutor y el visto bueno ya deben aparecer sobre su firma.
        $solicitud2 = $this->crearSolicitud($conductores[1], $vehiculoHr, 'CORRECTIVO', 'Fuga de aceite hidráulico visible bajo el equipo.', 'Revisar con urgencia, se usa a diario.');
        $this->emitirOrden($solicitud2, $jefeArea, $tecnico1, $vehiculoHr);

        // ── Caso 3: orden culminada, pocas líneas de detalle y textos largos ──
        // Ejercita el ajuste de línea (MultiCell) de la descripción/observaciones.
        $solicitud3 = $this->crearSolicitud(
            $conductores[2],
            $vehiculoKm,
            'PREVENTIVO',
            'Al realizar la inspección de rutina se detectó que el nivel de refrigerante '.
                'está por debajo del mínimo y que una de las mangueras del sistema de '.
                'enfriamiento presenta signos de desgaste y una fuga leve en la conexión '.
                'inferior, por lo que se solicita revisión completa del sistema antes de '.
                'que el equipo vuelva a operar en el turno siguiente.',
            'Se recomienda además revisar el estado de la correa del ventilador, que '.
                'ya presenta ruido, para no generar una segunda parada.'
        );
        $orden3 = $this->emitirOrden($solicitud3, $jefeArea, $tecnico2, $vehiculoKm);
        $this->culminarOrden($orden3);
        $this->agregarDetalle($orden3, $tiposMantenimiento, $repuestos, $vehiculoKm, 3);

        // ── Caso 4: orden culminada con más líneas de detalle que las que ──
        // entran en la tabla (14 > $maxFilas=10 en el reporte): debe truncar.
        $solicitud4 = $this->crearSolicitud($conductores[0], $vehiculoHr, 'CORRECTIVO', 'Motor no enciende, batería descargada repetidas veces.', null);
        $orden4 = $this->emitirOrden($solicitud4, $jefeArea, $tecnico1, $vehiculoHr);
        $this->culminarOrden($orden4);
        $this->agregarDetalle($orden4, $tiposMantenimiento, $repuestos, $vehiculoHr, 14);

        // ── Caso 5: orden verificada con exactamente 10 líneas (llena la ──
        // tabla justo al límite, sin filas en blanco) y sin observaciones.
        $solicitud5 = $this->crearSolicitud($conductores[1], $vehiculoKm, 'PREVENTIVO', 'Mantenimiento preventivo programado según kilometraje.', null);
        $orden5 = $this->emitirOrden($solicitud5, $jefeArea, $tecnico2, $vehiculoKm);
        $this->culminarOrden($orden5);
        $orden5->update(['estado_orden' => 'VERIFICADO']);
        $this->agregarDetalle($orden5, $tiposMantenimiento, $repuestos, $vehiculoKm, 10);

        $this->command->info('✓ Solicitudes de mantenimiento de prueba creadas exitosamente');
    }

    private function crearSolicitud(User $conductor, Vehiculo $vehiculo, string $tipoMantenimiento, string $descripcion, ?string $observacion): SolicitudMantenimiento
    {
        // SolicitudMantenimiento::boot() lee Auth::user()->id_persona/Auth::id()
        // para id_conductor/id_usuario_registra, así que hace falta "loguearse"
        // como el conductor antes de crear (mismo criterio que los feature tests).
        Auth::login($conductor);

        $solicitud = SolicitudMantenimiento::create([
            'id_vehiculo' => $vehiculo->id,
            'tipo_mantenimiento' => $tipoMantenimiento,
            'descripcion_problema' => $descripcion,
            'observacion' => $observacion,
            'fecha_solicitud' => now(),
            'kilometraje_actual' => $vehiculo->tipo_medicion === 'kilometraje' ? random_int(10000, 120000) : null,
            'horometro_actual' => $vehiculo->tipo_medicion === 'horometro' ? random_int(500, 8000) : null,
        ]);

        Auth::logout();

        return $solicitud;
    }

    private function emitirOrden(SolicitudMantenimiento $solicitud, User $jefeArea, User $tecnico, Vehiculo $vehiculo): OrdenTrabajo
    {
        // OrdenTrabajo::boot() lee auth()->id() para id_usuario_emite.
        Auth::login($jefeArea);

        $orden = OrdenTrabajo::create([
            'id_solicitud_mantenimiento' => $solicitud->id,
            'id_vehiculo' => $vehiculo->id,
            'id_usuario_ejecuta' => $tecnico->id,
            'tipo_mantenimiento' => $solicitud->tipo_mantenimiento,
            'nota_emisor' => 'Coordinar con el conductor la disponibilidad del equipo.',
            'kilometraje_actual' => $solicitud->kilometraje_actual,
            'horometro_actual' => $solicitud->horometro_actual,
        ]);

        $solicitud->update(['estado' => 'APROBADA']);

        Auth::logout();

        return $orden;
    }

    private function culminarOrden(OrdenTrabajo $orden): void
    {
        $orden->update([
            'estado_orden' => 'CULMINADO',
            'fecha_ejecucion' => now()->subHours(3),
            'fecha_culminacion' => now(),
            'observacion' => 'Trabajo finalizado sin novedad.',
        ]);
    }

    private function agregarDetalle(OrdenTrabajo $orden, $tiposMantenimiento, $repuestos, Vehiculo $vehiculo, int $cantidad): void
    {
        $lecturaBase = $vehiculo->tipo_medicion === 'kilometraje'
            ? ($orden->kilometraje_actual ?? 50000)
            : ($orden->horometro_actual ?? 2000);

        for ($i = 0; $i < $cantidad; $i++) {
            // Alterna ítems con repuesto del catálogo y mano de obra pura
            // (id_repuesto null), igual que se registra desde la ejecución.
            $repuesto = $i % 3 === 0 ? null : $repuestos->random();

            DetalleMantenimiento::create([
                'id_orden_trabajo' => $orden->id,
                'id_repuesto' => $repuesto?->id,
                'id_tipo_mantenimiento' => $tiposMantenimiento->random()->id,
                'fecha' => now()->subDays($cantidad - $i),
                'horometro' => $vehiculo->tipo_medicion === 'horometro' ? $lecturaBase + $i * 2 : null,
                'kilometraje' => $vehiculo->tipo_medicion === 'kilometraje' ? $lecturaBase + $i * 15 : null,
                'cantidad' => random_int(1, 5),
            ]);
        }
    }
}
