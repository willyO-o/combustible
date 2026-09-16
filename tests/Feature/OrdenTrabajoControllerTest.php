<?php

namespace Tests\Feature;

use App\Models\Asignacion;
use App\Models\Conductor;
use App\Models\DetalleMantenimiento;
use App\Models\OrdenTrabajo;
use App\Models\ParametrosEmpresa;
use App\Models\Persona;
use App\Models\SolicitudMantenimiento;
use App\Models\Taller;
use App\Models\TipoMantenimiento;
use App\Models\User;
use App\Models\Vehiculo;
use App\Notifications\OrdenTrabajoAsignadaNotification;
use App\Notifications\OrdenTrabajoCulminadaNotification;
use App\Notifications\OrdenTrabajoVerificadaNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrdenTrabajoControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'jefe-area', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'tecnico-mantenimiento', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');

        // Los modelos SolicitudMantenimiento/OrdenTrabajo asignan el usuario
        // autenticado (id_usuario_registra/id_usuario_emite) al crearse.
        $this->actingAs($this->admin);

        // OrdenTrabajo::calcularGestion()/getNroAttribute() leen este parámetro.
        ParametrosEmpresa::create([
            'nombre_empresa' => 'Plus Metals Ltda.',
            'direccion_empresa' => 'Calle Principal 123',
            'telefono_empresa' => '123456789',
            'correo_empresa' => 'info@miempresa.com',
            'nit_empresa' => '123456789',
            'parametros_vale' => ['tiempo_expiracion' => 1],
            'estado' => 'ACTIVO',
        ]);
    }

    private function crearSolicitud(array $overrides = []): SolicitudMantenimiento
    {
        return SolicitudMantenimiento::create(array_merge([
            'id_vehiculo' => Vehiculo::factory()->create()->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
            'descripcion_problema' => 'Cambio de aceite',
            'fecha_solicitud' => now(),
            'estado' => 'PENDIENTE',
        ], $overrides));
    }

    private function crearTecnico(): User
    {
        $tecnico = User::factory()->create();
        $tecnico->assignRole('tecnico-mantenimiento');

        return $tecnico;
    }

    private function crearOrden(array $overrides = []): OrdenTrabajo
    {
        return OrdenTrabajo::create(array_merge([
            'id_vehiculo' => Vehiculo::factory()->create()->id,
            'id_usuario_ejecuta' => $this->crearTecnico()->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
        ], $overrides));
    }

    private function asignarConductorAVehiculo(Vehiculo $vehiculo): Conductor
    {
        $persona = Persona::factory()->create();
        $conductor = Conductor::create(['id' => $persona->id, 'estado_conductor' => 'ACTIVO']);

        Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'ACTIVO',
        ]);

        return $conductor;
    }

    public function test_store_emite_una_orden_interna_y_aprueba_la_solicitud_origen(): void
    {
        $vehiculo = Vehiculo::factory()->create();
        $ejecutor = $this->crearTecnico();
        $solicitud = $this->crearSolicitud(['id_vehiculo' => $vehiculo->id]);

        $response = $this->actingAs($this->admin)->post(route('mantenimiento.ordenes.store'), [
            'id_vehiculo' => $vehiculo->id,
            'id_solicitud_mantenimiento' => $solicitud->id,
            'id_usuario_ejecuta' => $ejecutor->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
            'nota_emisor' => 'Revisar frenos',
            'kilometraje_actual' => 85000,
        ]);

        $orden = OrdenTrabajo::first();

        $response->assertRedirect(route('mantenimiento.ordenes.show', $orden));
        $this->assertNotNull($orden);
        $this->assertSame('PENDIENTE', $orden->estado_orden);
        $this->assertSame($this->admin->id, $orden->id_usuario_emite);
        $this->assertSame(1, $orden->nro_orden);
        $this->assertNull($orden->id_taller);
        $this->assertSame('INTERNO', $orden->tipo_orden);
        $this->assertSame('APROBADA', $solicitud->fresh()->estado);
    }

    public function test_store_notifica_al_tecnico_asignado(): void
    {
        Notification::fake();

        $ejecutor = $this->crearTecnico();

        $this->actingAs($this->admin)->post(route('mantenimiento.ordenes.store'), [
            'id_vehiculo' => Vehiculo::factory()->create()->id,
            'id_usuario_ejecuta' => $ejecutor->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
            'kilometraje_actual' => 1000,
        ])->assertRedirect();

        Notification::assertSentTo($ejecutor, OrdenTrabajoAsignadaNotification::class);
    }

    public function test_update_notifica_solo_cuando_cambia_el_tecnico_asignado(): void
    {
        Notification::fake();

        $tecnicoA = $this->crearTecnico();
        $tecnicoB = $this->crearTecnico();
        $orden = $this->crearOrden(['id_usuario_ejecuta' => $tecnicoA->id]);

        // Editar la orden sin tocar el responsable: no debe notificar.
        $this->actingAs($this->admin)->put(route('mantenimiento.ordenes.update', $orden), [
            'id_vehiculo' => $orden->id_vehiculo,
            'id_usuario_ejecuta' => $tecnicoA->id,
            'tipo_mantenimiento' => 'CORRECTIVO',
            'kilometraje_actual' => 1000,
        ])->assertRedirect();

        Notification::assertNothingSentTo($tecnicoA);

        // Reasignar a otro técnico: notifica al nuevo responsable.
        $this->actingAs($this->admin)->put(route('mantenimiento.ordenes.update', $orden), [
            'id_vehiculo' => $orden->id_vehiculo,
            'id_usuario_ejecuta' => $tecnicoB->id,
            'tipo_mantenimiento' => 'CORRECTIVO',
            'kilometraje_actual' => 1000,
        ])->assertRedirect();

        Notification::assertSentTo($tecnicoB, OrdenTrabajoAsignadaNotification::class);
    }

    public function test_store_con_solicitud_origen_ignora_vehiculo_y_categoria_manipulados(): void
    {
        $vehiculoSolicitud = Vehiculo::factory()->create();
        $vehiculoTrampa = Vehiculo::factory()->create();
        $solicitud = $this->crearSolicitud([
            'id_vehiculo' => $vehiculoSolicitud->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
            'kilometraje_actual' => 50000,
        ]);

        $response = $this->actingAs($this->admin)->post(route('mantenimiento.ordenes.store'), [
            'id_vehiculo' => $vehiculoTrampa->id,
            'id_solicitud_mantenimiento' => $solicitud->id,
            'id_usuario_ejecuta' => $this->crearTecnico()->id,
            'tipo_mantenimiento' => 'CORRECTIVO',
            'kilometraje_actual' => 999999,
        ]);

        $response->assertRedirect();
        $orden = OrdenTrabajo::first();

        $this->assertSame($vehiculoSolicitud->id, $orden->id_vehiculo);
        $this->assertSame('PREVENTIVO', $orden->tipo_mantenimiento);
        $this->assertSame(50000, $orden->kilometraje_actual);
    }

    /**
     * Sin solicitud de origen, store() genera una automáticamente (ya
     * APROBADA) con los datos del propio formulario, para que la orden
     * siempre quede vinculada a una solicitud (lo exige el reporte de
     * mantenimiento). Toca 2 tablas -> corre en transacción.
     */
    public function test_store_sin_solicitud_de_origen_genera_una_solicitud_aprobada_automaticamente(): void
    {
        $vehiculo = Vehiculo::factory()->create();

        $response = $this->actingAs($this->admin)->post(route('mantenimiento.ordenes.store'), [
            'id_vehiculo' => $vehiculo->id,
            'id_usuario_ejecuta' => $this->crearTecnico()->id,
            'tipo_mantenimiento' => 'CORRECTIVO',
            'nota_emisor' => 'Fuga de aceite detectada',
            'kilometraje_actual' => 12345,
        ]);

        $response->assertRedirect();
        $orden = OrdenTrabajo::first();
        $this->assertNotNull($orden->id_solicitud_mantenimiento);

        $solicitud = SolicitudMantenimiento::find($orden->id_solicitud_mantenimiento);
        $this->assertNotNull($solicitud);
        $this->assertSame('APROBADA', $solicitud->estado);
        $this->assertSame($vehiculo->id, $solicitud->id_vehiculo);
        $this->assertSame('CORRECTIVO', $solicitud->tipo_mantenimiento);
        $this->assertSame('Fuga de aceite detectada', $solicitud->descripcion_problema);
        $this->assertSame(12345, $solicitud->kilometraje_actual);
        $this->assertNull($solicitud->id_conductor);
    }

    /**
     * Sin nota del emisor, la solicitud generada automáticamente igual
     * necesita una descripcion_problema (NOT NULL en la BD): se completa con
     * un texto por defecto en vez de fallar.
     */
    public function test_store_sin_solicitud_de_origen_ni_nota_completa_la_descripcion_por_defecto(): void
    {
        $this->actingAs($this->admin)->post(route('mantenimiento.ordenes.store'), [
            'id_vehiculo' => Vehiculo::factory()->create()->id,
            'id_usuario_ejecuta' => $this->crearTecnico()->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
            'kilometraje_actual' => 1000,
        ])->assertRedirect();

        $solicitud = SolicitudMantenimiento::first();
        $this->assertNotEmpty($solicitud->descripcion_problema);
    }

    /**
     * Al emitir una orden sin solicitud de origen, el select de Conductor
     * (junto al de vehículo) permite elegir uno de los realmente asignados;
     * ese conductor pasa tanto a la orden como a la solicitud generada.
     */
    public function test_store_sin_solicitud_de_origen_usa_el_conductor_elegido_para_la_solicitud_generada(): void
    {
        $vehiculo = Vehiculo::factory()->create();
        $conductor = $this->asignarConductorAVehiculo($vehiculo);

        $response = $this->actingAs($this->admin)->post(route('mantenimiento.ordenes.store'), [
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'id_usuario_ejecuta' => $this->crearTecnico()->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
            'kilometraje_actual' => 1000,
        ]);

        $response->assertRedirect();
        $orden = OrdenTrabajo::first();
        $this->assertSame($conductor->id, $orden->id_conductor);

        $solicitud = SolicitudMantenimiento::find($orden->id_solicitud_mantenimiento);
        $this->assertSame($conductor->id, $solicitud->id_conductor);
    }

    /**
     * El conductor elegido junto al vehículo (sin solicitud de origen) debe
     * estar realmente asignado a ese vehículo; uno de otro vehículo se
     * rechaza en la validación (una sola consulta exists, ver
     * OrdenTrabajoRequest::rules()).
     */
    public function test_store_rechaza_un_conductor_no_asignado_al_vehiculo_sin_solicitud_de_origen(): void
    {
        $vehiculo = Vehiculo::factory()->create();
        $otroVehiculo = Vehiculo::factory()->create();
        $conductorDeOtroVehiculo = $this->asignarConductorAVehiculo($otroVehiculo);

        $response = $this->actingAs($this->admin)->post(route('mantenimiento.ordenes.store'), [
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductorDeOtroVehiculo->id,
            'id_usuario_ejecuta' => $this->crearTecnico()->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
            'kilometraje_actual' => 1000,
        ]);

        $response->assertSessionHasErrors('id_conductor');
        $this->assertDatabaseCount('orden_trabajo', 0);
        $this->assertDatabaseCount('solicitud_mantenimiento', 0);
    }

    /**
     * El conductor de una solicitud de origen no se re-valida contra la
     * asignación actual del vehículo (pudo reasignarse desde que se generó
     * la solicitud): no debe bloquear la emisión de la orden.
     */
    public function test_store_con_solicitud_de_origen_no_revalida_la_asignacion_del_conductor(): void
    {
        $vehiculo = Vehiculo::factory()->create();
        // El conductor de la solicitud ya NO está asignado a este vehículo.
        $otroVehiculo = Vehiculo::factory()->create();
        $conductorReasignado = $this->asignarConductorAVehiculo($otroVehiculo);
        $solicitud = $this->crearSolicitud([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductorReasignado->id,
        ]);

        $response = $this->actingAs($this->admin)->post(route('mantenimiento.ordenes.store'), [
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductorReasignado->id,
            'id_solicitud_mantenimiento' => $solicitud->id,
            'id_usuario_ejecuta' => $this->crearTecnico()->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
            'kilometraje_actual' => 1000,
        ]);

        $response->assertRedirect();
        $this->assertSame(1, OrdenTrabajo::count());
    }

    /**
     * Create.vue ofrece un combo de "Conductor" (visible cuando no se elige
     * solicitud de origen) poblado desde los conductores realmente asignados
     * a cada vehículo, resueltos en una sola consulta agrupada.
     */
    public function test_create_incluye_los_conductores_asignados_de_cada_vehiculo(): void
    {
        $vehiculo = Vehiculo::factory()->create();
        $conductor = $this->asignarConductorAVehiculo($vehiculo);
        Vehiculo::factory()->create(); // sin conductor asignado

        $response = $this->get(route('mantenimiento.ordenes.create'));

        $response->assertOk();
        $vehiculos = collect($response->viewData('page')['props']['vehiculos']);
        $opcion = $vehiculos->firstWhere('id', $vehiculo->id);

        $this->assertNotNull($opcion);
        $this->assertSame([$conductor->id], collect($opcion['conductoresAsignados'])->pluck('id')->all());
    }

    /**
     * Caso límite: por defecto store() deja la solicitud origen en APROBADA
     * (ver test_store_emite_una_orden_interna_y_aprueba_la_solicitud_origen),
     * lo que ya la saca del listado por el filtro estado=PENDIENTE. Este test
     * fuerza el estado PENDIENTE con una orden ya enganchada para blindar
     * también ese escenario (whereDoesntHave('ordenTrabajo')).
     */
    public function test_create_no_lista_una_solicitud_que_ya_tiene_orden_de_trabajo(): void
    {
        $solicitud = $this->crearSolicitud();
        $this->crearOrden(['id_solicitud_mantenimiento' => $solicitud->id]);

        $response = $this->get(route('mantenimiento.ordenes.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('OrdenTrabajo/Create')
            ->has('solicitudesPendientes', 0)
        );
    }

    public function test_create_no_preselecciona_una_solicitud_que_ya_tiene_orden(): void
    {
        $solicitud = $this->crearSolicitud();
        $this->crearOrden(['id_solicitud_mantenimiento' => $solicitud->id]);

        $response = $this->get(route('mantenimiento.ordenes.create', ['solicitud' => $solicitud->id]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('OrdenTrabajo/Create')
            ->where('solicitudPreseleccionada', null)
        );
    }

    public function test_store_rechaza_una_solicitud_que_ya_tiene_una_orden_de_trabajo(): void
    {
        $solicitud = $this->crearSolicitud();
        $this->crearOrden(['id_solicitud_mantenimiento' => $solicitud->id]);

        $response = $this->post(route('mantenimiento.ordenes.store'), [
            'id_vehiculo' => Vehiculo::factory()->create()->id,
            'id_solicitud_mantenimiento' => $solicitud->id,
            'id_usuario_ejecuta' => $this->crearTecnico()->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
            'kilometraje_actual' => 1000,
        ]);

        $response->assertSessionHasErrors('id_solicitud_mantenimiento');
        $this->assertSame(1, OrdenTrabajo::where('id_solicitud_mantenimiento', $solicitud->id)->count());
    }

    /**
     * Regresión: la validación nueva de id_solicitud_mantenimiento no debe
     * bloquear editar una orden que ya trae enganchada su propia solicitud
     * origen (que, por definición, ya tiene esta misma orden asignada).
     */
    public function test_update_no_falla_por_la_propia_solicitud_ya_asignada_a_la_orden(): void
    {
        $solicitud = $this->crearSolicitud();
        $orden = $this->crearOrden(['id_solicitud_mantenimiento' => $solicitud->id]);

        $response = $this->put(route('mantenimiento.ordenes.update', $orden), [
            'id_vehiculo' => $orden->id_vehiculo,
            'id_solicitud_mantenimiento' => $solicitud->id,
            'id_usuario_ejecuta' => $orden->id_usuario_ejecuta,
            'tipo_mantenimiento' => 'CORRECTIVO',
            'kilometraje_actual' => 1500,
        ]);

        $response->assertRedirect(route('mantenimiento.ordenes.show', $orden));
        $this->assertSame('CORRECTIVO', $orden->fresh()->tipo_mantenimiento);
    }

    /**
     * Guarda de regresión de eficiencia: validar id_solicitud_mantenimiento
     * (estado + "sin orden ya asignada") debe resolverse en UNA sola
     * consulta (whereDoesntHave -> NOT EXISTS en la BD), no con
     * exists()+find()+exists() por separado. Ver .ai/rules sobre eficiencia
     * de consultas/condicionales.
     */
    public function test_valida_la_solicitud_de_origen_en_una_sola_consulta(): void
    {
        $solicitud = $this->crearSolicitud();

        DB::enableQueryLog();

        $this->post(route('mantenimiento.ordenes.store'), [
            'id_vehiculo' => Vehiculo::factory()->create()->id,
            'id_solicitud_mantenimiento' => $solicitud->id,
            'id_usuario_ejecuta' => $this->crearTecnico()->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
            'kilometraje_actual' => 1000,
        ]);

        // Sólo cuenta lecturas (SELECT) a la tabla solicitud_mantenimiento:
        // el INSERT de orden_trabajo menciona la columna id_solicitud_mantenimiento
        // (falso positivo por substring) y el UPDATE final que la aprueba es
        // una escritura de negocio, no una lectura de filtrado/validación.
        $consultasSolicitud = collect(DB::getQueryLog())
            ->filter(fn ($q) => str_starts_with(strtolower($q['query']), 'select')
                && str_contains($q['query'], 'solicitud_mantenimiento'))
            ->count();

        DB::disableQueryLog();

        // 1 consulta en la validación (whereDoesntHave) + 1 en store() al
        // copiar los datos de la solicitud origen (findOrFail) = 2, nunca más.
        $this->assertLessThanOrEqual(2, $consultasSolicitud);
    }

    public function test_store_orden_externa_cuando_se_asigna_taller(): void
    {
        $taller = Taller::create([
            'razon_social' => 'Taller Central',
            'nit' => '123456',
            'estado_taller' => 'ACTIVO',
        ]);

        $response = $this->actingAs($this->admin)->post(route('mantenimiento.ordenes.store'), [
            'id_vehiculo' => Vehiculo::factory()->create()->id,
            'id_taller' => $taller->id,
            'id_usuario_ejecuta' => $this->crearTecnico()->id,
            'tipo_mantenimiento' => 'CORRECTIVO',
            'kilometraje_actual' => 42000,
        ]);

        $response->assertRedirect();
        $orden = OrdenTrabajo::first();

        $this->assertSame($taller->id, $orden->id_taller);
        $this->assertSame('EXTERNO', $orden->tipo_orden);
    }

    public function test_la_numeracion_de_orden_es_secuencial_por_gestion(): void
    {
        $primera = $this->crearOrden();
        $segunda = $this->crearOrden();

        $this->assertSame(1, $primera->nro_orden);
        $this->assertSame(2, $segunda->nro_orden);
        $this->assertSame($primera->gestion, $segunda->gestion);
    }

    public function test_cambiar_estado_a_en_ejecucion_registra_la_fecha_de_ejecucion(): void
    {
        $orden = $this->crearOrden();

        $response = $this->actingAs($this->admin)
            ->patch(route('mantenimiento.ordenes.estado', $orden), ['estado_orden' => 'EN_EJECUCION']);

        $response->assertRedirect();
        $orden->refresh();
        $this->assertSame('EN_EJECUCION', $orden->estado_orden);
        $this->assertNotNull($orden->fecha_ejecucion);
    }

    public function test_store_detalle_registra_un_item_del_detalle(): void
    {
        $orden = $this->crearOrden();
        $tipoMantenimiento = TipoMantenimiento::create([
            'tipo_mantenimiento' => 'Cambio de aceite',
            'estado_tipo_mantenimiento' => 'ACTIVO',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('mantenimiento.ordenes.ejecucion.detalles.store', $orden), [
                'id_tipo_mantenimiento' => $tipoMantenimiento->id,
                'fecha' => now()->toDateString(),
                // El vehículo de crearOrden() usa tipo_medicion "kilometraje" por
                // defecto (VehiculoFactory), así que ese es el campo exigido.
                'kilometraje' => 1250.5,
                'cantidad' => 2,
            ]);

        $response->assertRedirect(route('mantenimiento.ordenes.ejecucion.create', $orden));
        $this->assertSame(1, DetalleMantenimiento::where('id_orden_trabajo', $orden->id)->count());

        $detalle = DetalleMantenimiento::first();
        $this->assertSame($orden->id, $detalle->id_orden_trabajo);
        $this->assertSame(2, $detalle->cantidad);
        $this->assertSame('1250.50', (string) $detalle->kilometraje);
    }

    public function test_la_pantalla_de_ejecucion_solo_ofrece_tipos_de_mantenimiento_de_taller(): void
    {
        $orden = $this->crearOrden();
        $taller = TipoMantenimiento::create([
            'tipo_mantenimiento' => 'Cambio de aceite',
            'estado_tipo_mantenimiento' => 'ACTIVO',
            'ambito' => 'taller',
        ]);
        TipoMantenimiento::create([
            'tipo_mantenimiento' => 'Nivel de aceite',
            'estado_tipo_mantenimiento' => 'ACTIVO',
            'ambito' => 'operacion_diaria',
            'tipo_valor' => 'booleano',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('mantenimiento.ordenes.ejecucion.create', $orden));

        $response->assertOk();
        $ids = collect($response->viewData('page')['props']['tiposMantenimiento'])->pluck('id');
        $this->assertTrue($ids->contains($taller->id));
        $this->assertCount(1, $ids);
    }

    public function test_store_detalle_rechaza_un_tipo_de_mantenimiento_de_operacion_diaria(): void
    {
        $orden = $this->crearOrden();
        $opDiaria = TipoMantenimiento::create([
            'tipo_mantenimiento' => 'Nivel de aceite',
            'estado_tipo_mantenimiento' => 'ACTIVO',
            'ambito' => 'operacion_diaria',
            'tipo_valor' => 'booleano',
        ]);

        $response = $this->actingAs($this->admin)
            ->from(route('mantenimiento.ordenes.ejecucion.create', $orden))
            ->post(route('mantenimiento.ordenes.ejecucion.detalles.store', $orden), [
                'id_tipo_mantenimiento' => $opDiaria->id,
                'fecha' => now()->toDateString(),
                'kilometraje' => 1000,
                'cantidad' => 1,
            ]);

        $response->assertSessionHasErrors('id_tipo_mantenimiento');
    }

    public function test_store_detalle_exige_la_lectura_que_corresponde_al_tipo_de_medicion_del_vehiculo(): void
    {
        $vehiculo = Vehiculo::factory()->create(['tipo_medicion' => 'horometro']);
        $orden = $this->crearOrden(['id_vehiculo' => $vehiculo->id]);
        $tipoMantenimiento = TipoMantenimiento::create([
            'tipo_mantenimiento' => 'Cambio de aceite',
            'estado_tipo_mantenimiento' => 'ACTIVO',
        ]);

        // El vehículo mide por horómetro: enviar kilometraje en su lugar debe
        // fallar la validación (horometro es el campo exigido).
        $response = $this->actingAs($this->admin)
            ->from(route('mantenimiento.ordenes.ejecucion.create', $orden))
            ->post(route('mantenimiento.ordenes.ejecucion.detalles.store', $orden), [
                'id_tipo_mantenimiento' => $tipoMantenimiento->id,
                'fecha' => now()->toDateString(),
                'kilometraje' => 500,
                'cantidad' => 1,
            ]);

        $response->assertSessionHasErrors('horometro');
        $this->assertSame(0, DetalleMantenimiento::where('id_orden_trabajo', $orden->id)->count());

        $response = $this->actingAs($this->admin)
            ->post(route('mantenimiento.ordenes.ejecucion.detalles.store', $orden), [
                'id_tipo_mantenimiento' => $tipoMantenimiento->id,
                'fecha' => now()->toDateString(),
                'horometro' => 500,
                'cantidad' => 1,
            ]);

        $response->assertRedirect(route('mantenimiento.ordenes.ejecucion.create', $orden));
        $this->assertSame(1, DetalleMantenimiento::where('id_orden_trabajo', $orden->id)->count());
    }

    public function test_update_detalle_permite_corregir_un_item(): void
    {
        $orden = $this->crearOrden();
        $tipoMantenimiento = TipoMantenimiento::create([
            'tipo_mantenimiento' => 'Cambio de aceite',
            'estado_tipo_mantenimiento' => 'ACTIVO',
        ]);
        $detalle = $orden->detalles()->create([
            'id_tipo_mantenimiento' => $tipoMantenimiento->id,
            'fecha' => now()->toDateString(),
            'cantidad' => 1,
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('mantenimiento.ordenes.ejecucion.detalles.update', [$orden, $detalle]), [
                'id_tipo_mantenimiento' => $tipoMantenimiento->id,
                'fecha' => now()->toDateString(),
                'kilometraje' => 1000,
                'cantidad' => 5,
            ]);

        $response->assertRedirect(route('mantenimiento.ordenes.ejecucion.create', $orden));
        $this->assertSame(5, $detalle->fresh()->cantidad);
    }

    public function test_destroy_detalle_elimina_un_item(): void
    {
        $orden = $this->crearOrden();
        $tipoMantenimiento = TipoMantenimiento::create([
            'tipo_mantenimiento' => 'Cambio de aceite',
            'estado_tipo_mantenimiento' => 'ACTIVO',
        ]);
        $detalle = $orden->detalles()->create([
            'id_tipo_mantenimiento' => $tipoMantenimiento->id,
            'fecha' => now()->toDateString(),
            'cantidad' => 1,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('mantenimiento.ordenes.ejecucion.detalles.destroy', [$orden, $detalle]));

        $response->assertRedirect(route('mantenimiento.ordenes.ejecucion.create', $orden));
        $this->assertDatabaseMissing('detalle_mantenimiento', ['id' => $detalle->id]);
    }

    public function test_culminar_ejecucion_exige_al_menos_un_item_de_detalle(): void
    {
        $orden = $this->crearOrden();

        $response = $this->actingAs($this->admin)
            ->post(route('mantenimiento.ordenes.ejecucion.culminar', $orden), [
                'kilometraje_actual' => 90000,
            ]);

        $response->assertRedirect();
        $this->assertSame('PENDIENTE', $orden->fresh()->estado_orden);
    }

    public function test_culminar_ejecucion_culmina_la_orden_con_las_lecturas_finales(): void
    {
        $orden = $this->crearOrden();
        $tipoMantenimiento = TipoMantenimiento::create([
            'tipo_mantenimiento' => 'Cambio de aceite',
            'estado_tipo_mantenimiento' => 'ACTIVO',
        ]);
        $orden->detalles()->create([
            'id_tipo_mantenimiento' => $tipoMantenimiento->id,
            'fecha' => now()->toDateString(),
            'cantidad' => 1,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('mantenimiento.ordenes.ejecucion.culminar', $orden), [
                'kilometraje_actual' => 90000,
                'observacion' => 'Trabajo finalizado sin novedad',
            ]);

        $response->assertRedirect(route('mantenimiento.ordenes.show', $orden));
        $orden->refresh();

        $this->assertSame('CULMINADO', $orden->estado_orden);
        $this->assertNotNull($orden->fecha_culminacion);
        $this->assertNotNull($orden->fecha_ejecucion);
        $this->assertSame(90000, $orden->kilometraje_actual);
    }

    public function test_culminar_ejecucion_notifica_a_quien_emitio_la_orden(): void
    {
        Notification::fake();

        // crearOrden() se emite bajo $this->admin (ver setUp: $this->actingAs($this->admin)).
        $orden = $this->crearOrden();
        $tipoMantenimiento = TipoMantenimiento::create([
            'tipo_mantenimiento' => 'Cambio de aceite',
            'estado_tipo_mantenimiento' => 'ACTIVO',
        ]);
        $orden->detalles()->create([
            'id_tipo_mantenimiento' => $tipoMantenimiento->id,
            'fecha' => now()->toDateString(),
            'cantidad' => 1,
        ]);

        $this->actingAs($this->admin)
            ->post(route('mantenimiento.ordenes.ejecucion.culminar', $orden), [
                'kilometraje_actual' => 90000,
            ])->assertRedirect();

        Notification::assertSentTo($this->admin, OrdenTrabajoCulminadaNotification::class);
    }

    public function test_no_se_puede_modificar_el_detalle_de_una_orden_ya_culminada(): void
    {
        // OrdenTrabajo::boot() fuerza estado_orden = PENDIENTE al crear, así que
        // el estado CULMINADO se fija en un segundo paso, después de crear.
        $orden = $this->crearOrden();
        $orden->update(['estado_orden' => 'CULMINADO']);
        $tipoMantenimiento = TipoMantenimiento::create([
            'tipo_mantenimiento' => 'Cambio de aceite',
            'estado_tipo_mantenimiento' => 'ACTIVO',
        ]);
        $detalle = DetalleMantenimiento::create([
            'id_orden_trabajo' => $orden->id,
            'id_tipo_mantenimiento' => $tipoMantenimiento->id,
            'fecha' => now()->toDateString(),
            'cantidad' => 1,
        ]);

        $this->actingAs($this->admin)
            ->post(route('mantenimiento.ordenes.ejecucion.detalles.store', $orden), [
                'id_tipo_mantenimiento' => $tipoMantenimiento->id,
                'fecha' => now()->toDateString(),
                'kilometraje' => 1000,
                'cantidad' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->put(route('mantenimiento.ordenes.ejecucion.detalles.update', [$orden, $detalle]), [
                'id_tipo_mantenimiento' => $tipoMantenimiento->id,
                'fecha' => now()->toDateString(),
                'kilometraje' => 1000,
                'cantidad' => 9,
            ])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->delete(route('mantenimiento.ordenes.ejecucion.detalles.destroy', [$orden, $detalle]))
            ->assertForbidden();

        $this->assertSame(1, $detalle->fresh()->cantidad);
        $this->assertSame(1, DetalleMantenimiento::where('id_orden_trabajo', $orden->id)->count());
    }

    public function test_index_filtra_ordenes_externas(): void
    {
        $taller = Taller::create([
            'razon_social' => 'Taller Externo',
            'nit' => '999999',
            'estado_taller' => 'ACTIVO',
        ]);
        $this->crearOrden(); // interna
        $this->crearOrden(['id_taller' => $taller->id]); // externa

        $response = $this->actingAs($this->admin)
            ->get(route('mantenimiento.ordenes.index', ['tipo_orden' => 'EXTERNO']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('OrdenTrabajo/Index')
            ->has('ordenes.data', 1)
            ->where('ordenes.data.0.tipo_orden', 'EXTERNO')
        );
    }

    public function test_create_esta_bloqueado_para_roles_sin_permiso(): void
    {
        $conductor = User::factory()->create();
        $conductor->assignRole('conductor');

        $response = $this->actingAs($conductor)->get(route('mantenimiento.ordenes.create'));

        $response->assertForbidden();
    }

    public function test_store_esta_bloqueado_para_roles_sin_permiso(): void
    {
        $conductor = User::factory()->create();
        $conductor->assignRole('conductor');
        $vehiculo = Vehiculo::factory()->create();
        $ejecutor = $this->crearTecnico();

        $response = $this->actingAs($conductor)->post(route('mantenimiento.ordenes.store'), [
            'id_vehiculo' => $vehiculo->id,
            'id_usuario_ejecuta' => $ejecutor->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('orden_trabajo', 0);
    }

    public function test_create_es_accesible_para_un_jefe_de_area(): void
    {
        $jefe = User::factory()->create();
        $jefe->assignRole('jefe-area');

        $response = $this->actingAs($jefe)->get(route('mantenimiento.ordenes.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('OrdenTrabajo/Create')
        );
    }

    public function test_el_combo_de_usuarios_solo_lista_tecnicos_de_mantenimiento_activos(): void
    {
        $tecnicoActivo = $this->crearTecnico();
        $tecnicoInactivo = $this->crearTecnico();
        $tecnicoInactivo->update(['estado_usuario' => 'INACTIVO']);
        User::factory()->create(); // sin rol, no debe listarse

        $response = $this->actingAs($this->admin)->get(route('mantenimiento.ordenes.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('usuarios', 1)
            ->where('usuarios.0.id', $tecnicoActivo->id)
        );
    }

    public function test_store_rechaza_un_responsable_de_ejecucion_sin_rol_tecnico(): void
    {
        $response = $this->actingAs($this->admin)->post(route('mantenimiento.ordenes.store'), [
            'id_vehiculo' => Vehiculo::factory()->create()->id,
            'id_usuario_ejecuta' => User::factory()->create()->id, // sin rol tecnico-mantenimiento
            'tipo_mantenimiento' => 'PREVENTIVO',
            'kilometraje_actual' => 1000,
        ]);

        $response->assertSessionHasErrors('id_usuario_ejecuta');
        $this->assertDatabaseCount('orden_trabajo', 0);
    }

    public function test_un_tecnico_solo_ve_en_el_index_las_ordenes_que_tiene_asignadas(): void
    {
        $tecnico = $this->crearTecnico();
        $ordenPropia = $this->crearOrden(['id_usuario_ejecuta' => $tecnico->id]);
        $this->crearOrden(); // asignada a otro técnico

        $response = $this->actingAs($tecnico)->get(route('mantenimiento.ordenes.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('ordenes.data', 1)
            ->where('ordenes.data.0.id', $ordenPropia->id)
        );
    }

    public function test_un_tecnico_no_puede_ver_una_orden_que_no_tiene_asignada(): void
    {
        $tecnico = $this->crearTecnico();
        $orden = $this->crearOrden(); // asignada a otro técnico

        $response = $this->actingAs($tecnico)->get(route('mantenimiento.ordenes.show', $orden));

        $response->assertForbidden();
    }

    public function test_un_tecnico_puede_ver_su_propia_orden_asignada(): void
    {
        $tecnico = $this->crearTecnico();
        $orden = $this->crearOrden(['id_usuario_ejecuta' => $tecnico->id]);

        $response = $this->actingAs($tecnico)->get(route('mantenimiento.ordenes.show', $orden));

        $response->assertOk();
    }

    public function test_un_tecnico_no_puede_editar_una_orden(): void
    {
        $tecnico = $this->crearTecnico();
        $orden = $this->crearOrden(['id_usuario_ejecuta' => $tecnico->id]);

        $this->actingAs($tecnico)->get(route('mantenimiento.ordenes.edit', $orden))
            ->assertForbidden();

        $this->actingAs($tecnico)->put(route('mantenimiento.ordenes.update', $orden), [
            'id_vehiculo' => $orden->id_vehiculo,
            'id_usuario_ejecuta' => $tecnico->id,
            'tipo_mantenimiento' => 'CORRECTIVO',
            'kilometraje_actual' => 1000,
        ])->assertForbidden();

        $this->assertSame('PREVENTIVO', $orden->fresh()->tipo_mantenimiento);
    }

    public function test_un_tecnico_puede_marcar_su_orden_en_ejecucion_pero_no_verificarla(): void
    {
        $tecnico = $this->crearTecnico();
        $orden = $this->crearOrden(['id_usuario_ejecuta' => $tecnico->id]);

        $this->actingAs($tecnico)
            ->patch(route('mantenimiento.ordenes.estado', $orden), ['estado_orden' => 'EN_EJECUCION'])
            ->assertRedirect();

        $this->assertSame('EN_EJECUCION', $orden->fresh()->estado_orden);

        $orden->update(['estado_orden' => 'CULMINADO']);

        $this->actingAs($tecnico)
            ->patch(route('mantenimiento.ordenes.estado', $orden), ['estado_orden' => 'VERIFICADO'])
            ->assertForbidden();

        $this->assertSame('CULMINADO', $orden->fresh()->estado_orden);
    }

    public function test_un_tecnico_no_puede_cambiar_el_estado_de_una_orden_que_no_tiene_asignada(): void
    {
        $tecnico = $this->crearTecnico();
        $orden = $this->crearOrden(); // asignada a otro técnico

        $this->actingAs($tecnico)
            ->patch(route('mantenimiento.ordenes.estado', $orden), ['estado_orden' => 'EN_EJECUCION'])
            ->assertForbidden();
    }

    public function test_solo_el_emisor_puede_verificar_una_orden(): void
    {
        $otroJefe = User::factory()->create();
        $otroJefe->assignRole('jefe-area');
        $orden = $this->crearOrden(); // emitida por $this->admin
        $orden->update(['estado_orden' => 'CULMINADO']);

        $this->actingAs($otroJefe)
            ->patch(route('mantenimiento.ordenes.estado', $orden), ['estado_orden' => 'VERIFICADO'])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->patch(route('mantenimiento.ordenes.estado', $orden), ['estado_orden' => 'VERIFICADO'])
            ->assertRedirect();

        $this->assertSame('VERIFICADO', $orden->fresh()->estado_orden);
    }

    public function test_cambiar_estado_a_culminado_notifica_a_quien_emitio_la_orden(): void
    {
        Notification::fake();

        $tecnico = $this->crearTecnico();
        // crearOrden() se emite bajo $this->admin (ver setUp). OrdenTrabajo::boot()
        // fuerza estado_orden = PENDIENTE al crear, así que EN_EJECUCION se fija
        // en un segundo paso.
        $orden = $this->crearOrden(['id_usuario_ejecuta' => $tecnico->id]);
        $orden->update(['estado_orden' => 'EN_EJECUCION']);

        $this->actingAs($tecnico)
            ->patch(route('mantenimiento.ordenes.estado', $orden), ['estado_orden' => 'CULMINADO'])
            ->assertRedirect();

        Notification::assertSentTo($this->admin, OrdenTrabajoCulminadaNotification::class);
    }

    public function test_verificar_una_orden_notifica_al_tecnico_asignado(): void
    {
        Notification::fake();

        $tecnico = $this->crearTecnico();
        $orden = $this->crearOrden(['id_usuario_ejecuta' => $tecnico->id]);
        $orden->update(['estado_orden' => 'CULMINADO']);

        $this->actingAs($this->admin)
            ->patch(route('mantenimiento.ordenes.estado', $orden), ['estado_orden' => 'VERIFICADO'])
            ->assertRedirect();

        Notification::assertSentTo($tecnico, OrdenTrabajoVerificadaNotification::class);
    }

    public function test_un_tecnico_solo_gestiona_el_detalle_de_sus_propias_ordenes(): void
    {
        $tecnico = $this->crearTecnico();
        $ordenAjena = $this->crearOrden(); // asignada a otro técnico
        $tipoMantenimiento = TipoMantenimiento::create([
            'tipo_mantenimiento' => 'Cambio de aceite',
            'estado_tipo_mantenimiento' => 'ACTIVO',
        ]);

        $this->actingAs($tecnico)->get(route('mantenimiento.ordenes.ejecucion.create', $ordenAjena))
            ->assertForbidden();

        $this->actingAs($tecnico)
            ->post(route('mantenimiento.ordenes.ejecucion.detalles.store', $ordenAjena), [
                'id_tipo_mantenimiento' => $tipoMantenimiento->id,
                'fecha' => now()->toDateString(),
                'cantidad' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($tecnico)
            ->post(route('mantenimiento.ordenes.ejecucion.culminar', $ordenAjena), [
                'kilometraje_actual' => 90000,
            ])
            ->assertForbidden();

        $this->assertSame('PENDIENTE', $ordenAjena->fresh()->estado_orden);
        $this->assertSame(0, DetalleMantenimiento::where('id_orden_trabajo', $ordenAjena->id)->count());
    }
}
