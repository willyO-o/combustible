<?php

namespace Tests\Feature;

use App\Models\DetalleMantenimiento;
use App\Models\OrdenTrabajo;
use App\Models\ParametrosEmpresa;
use App\Models\SolicitudMantenimiento;
use App\Models\Taller;
use App\Models\TipoMantenimiento;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
