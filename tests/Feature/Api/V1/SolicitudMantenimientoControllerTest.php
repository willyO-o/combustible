<?php

namespace Tests\Feature\Api\V1;

use App\Models\Asignacion;
use App\Models\Conductor;
use App\Models\OrdenTrabajo;
use App\Models\ParametrosEmpresa;
use App\Models\Persona;
use App\Models\SolicitudMantenimiento;
use App\Models\Taller;
use App\Models\User;
use App\Models\Vehiculo;
use App\Notifications\OrdenTrabajoAsignadaNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SolicitudMantenimientoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $permisoEmitirOrden = Permission::firstOrCreate(['name' => 'mantenimiento.ordenes.crear', 'guard_name' => 'web']);

        // Igual que UserSeeder: jefe-area y administrador pueden generar órdenes;
        // conductor y técnico no.
        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web'])->givePermissionTo($permisoEmitirOrden);
        Role::firstOrCreate(['name' => 'jefe-area', 'guard_name' => 'web'])->givePermissionTo($permisoEmitirOrden);
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'tecnico-mantenimiento', 'guard_name' => 'web']);

        // SolicitudMantenimiento::calcularGestion()/getNroAttribute() leen este parámetro.
        ParametrosEmpresa::create([
            'nombre_empresa' => 'Plus Metals Ltda.',
            'direccion_empresa' => 'Calle Principal 123',
            'telefono_empresa' => '123456789',
            'correo_empresa' => 'info@miempresa.com',
            'nit_empresa' => '123456789',
            'parametros_vale' => ['tiempo_expiracion' => 1, 'digitos_serie' => 6],
            'estado' => 'ACTIVO',
        ]);
    }

    private function crearConductor(): Conductor
    {
        $persona = Persona::factory()->create();

        return Conductor::create(['id' => $persona->id, 'estado_conductor' => 'ACTIVO']);
    }

    /**
     * El id_vehiculo de una solicitud sólo valida si el vehículo está asignado
     * (asignación ACTIVA) al conductor autenticado.
     */
    private function crearAsignacion(Conductor $conductor, Vehiculo $vehiculo): Asignacion
    {
        return Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'fecha_asignacion' => now(),
            'estado_asignacion' => 'ACTIVO',
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

    public function test_lista_las_solicitudes_de_mantenimiento(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $this->actingAs($admin, 'api');

        // El modelo asigna el conductor/usuario a partir del usuario autenticado al
        // crear (ver boot() de SolicitudMantenimiento), de ahí el actingAs() previo.
        $this->crearSolicitud();
        $this->crearSolicitud();

        $response = $this
            ->getJson(route('api.v1.solicitudes-mantenimiento.index'));

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_un_conductor_solo_ve_sus_propias_solicitudes(): void
    {
        $conductor = $this->crearConductor();
        $user = User::factory()->create(['id_persona' => $conductor->id]);
        $user->assignRole('conductor');

        $otroConductor = $this->crearConductor();
        $otroUser = User::factory()->create(['id_persona' => $otroConductor->id]);
        $otroUser->assignRole('conductor');

        // El modelo asigna id_conductor a partir del usuario autenticado al crear
        // (ver boot() de SolicitudMantenimiento), de ahí el actingAs() por cada una.
        $this->actingAs($user, 'api');
        $this->crearSolicitud();

        $this->actingAs($otroUser, 'api');
        $this->crearSolicitud(); // de otro conductor

        $response = $this->actingAs($user, 'api')
            ->getJson(route('api.v1.solicitudes-mantenimiento.index'));

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_crea_una_solicitud_de_mantenimiento(): void
    {
        $conductor = $this->crearConductor();
        $user = User::factory()->create(['id_persona' => $conductor->id]);
        $user->assignRole('conductor');
        $vehiculo = Vehiculo::factory()->create();
        $this->crearAsignacion($conductor, $vehiculo);

        $response = $this->actingAs($user, 'api')
            ->postJson(route('api.v1.solicitudes-mantenimiento.store'), [
                'id_vehiculo' => $vehiculo->id,
                'tipo_mantenimiento' => 'CORRECTIVO',
                'descripcion_problema' => 'Falla en el motor',
                'kilometraje_actual' => 15000,
                'fecha_solicitud' => now()->toDateString(),
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.estado', 'PENDIENTE');
        $response->assertJsonPath('data.id_usuario_registra', $user->id);
        $this->assertDatabaseHas('solicitud_mantenimiento', [
            'id_vehiculo' => $vehiculo->id,
            'descripcion_problema' => 'Falla en el motor',
            'estado' => 'PENDIENTE',
        ]);
    }

    /**
     * Un administrador (o jefe-area) ahora también puede registrar una
     * solicitud vía API, siempre que elija explícitamente id_conductor y que
     * ese conductor esté realmente asignado al vehículo (ver
     * SolicitudMantenimientoRequest::rules()).
     */
    public function test_administrador_puede_registrar_una_solicitud_eligiendo_conductor(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        $conductor = $this->crearConductor();
        $vehiculo = Vehiculo::factory()->create();
        $this->crearAsignacion($conductor, $vehiculo);

        $response = $this->actingAs($admin, 'api')
            ->postJson(route('api.v1.solicitudes-mantenimiento.store'), [
                'id_vehiculo' => $vehiculo->id,
                'id_conductor' => $conductor->id,
                'tipo_mantenimiento' => 'CORRECTIVO',
                'descripcion_problema' => 'Falla en el motor',
                'kilometraje_actual' => 15000,
                'fecha_solicitud' => now()->toDateString(),
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.id_conductor', $conductor->id);
        $response->assertJsonPath('data.id_usuario_registra', $admin->id);
    }

    public function test_store_rechaza_a_un_administrador_que_no_elige_conductor(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $vehiculo = Vehiculo::factory()->create();

        $response = $this->actingAs($admin, 'api')
            ->postJson(route('api.v1.solicitudes-mantenimiento.store'), [
                'id_vehiculo' => $vehiculo->id,
                'tipo_mantenimiento' => 'CORRECTIVO',
                'descripcion_problema' => 'Falla en el motor',
                'fecha_solicitud' => now()->toDateString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['id_conductor']);
        $this->assertDatabaseCount('solicitud_mantenimiento', 0);
    }

    public function test_store_rechaza_a_usuarios_sin_ningun_rol_habilitado(): void
    {
        $sinRol = User::factory()->create();
        $vehiculo = Vehiculo::factory()->create();

        $response = $this->actingAs($sinRol, 'api')
            ->postJson(route('api.v1.solicitudes-mantenimiento.store'), [
                'id_vehiculo' => $vehiculo->id,
                'tipo_mantenimiento' => 'CORRECTIVO',
                'descripcion_problema' => 'Falla en el motor',
                'fecha_solicitud' => now()->toDateString(),
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('solicitud_mantenimiento', 0);
    }

    public function test_una_solicitud_creada_por_un_conductor_se_asocia_automaticamente(): void
    {
        $conductor = $this->crearConductor();
        $user = User::factory()->create(['id_persona' => $conductor->id]);
        $user->assignRole('conductor');
        $vehiculo = Vehiculo::factory()->create();
        $this->crearAsignacion($conductor, $vehiculo);

        $response = $this->actingAs($user, 'api')
            ->postJson(route('api.v1.solicitudes-mantenimiento.store'), [
                'id_vehiculo' => $vehiculo->id,
                'tipo_mantenimiento' => 'PREVENTIVO',
                'descripcion_problema' => 'Mantenimiento programado',
                'kilometraje_actual' => 15000,
                'fecha_solicitud' => now()->toDateString(),
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.id_conductor', $conductor->id);
    }

    public function test_valida_los_campos_requeridos_al_crear(): void
    {
        $conductor = $this->crearConductor();
        $user = User::factory()->create(['id_persona' => $conductor->id]);
        $user->assignRole('conductor');

        $response = $this->actingAs($user, 'api')
            ->postJson(route('api.v1.solicitudes-mantenimiento.store'), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['id_vehiculo', 'tipo_mantenimiento', 'descripcion_problema']);
    }

    public function test_actualiza_una_solicitud_pendiente(): void
    {
        $conductor = $this->crearConductor();
        $user = User::factory()->create(['id_persona' => $conductor->id]);
        $user->assignRole('conductor');
        $vehiculo = Vehiculo::factory()->create();
        $this->crearAsignacion($conductor, $vehiculo);
        $this->actingAs($user, 'api');

        $solicitud = $this->crearSolicitud();

        $response = $this
            ->putJson(route('api.v1.solicitudes-mantenimiento.update', $solicitud->id), [
                'id_vehiculo' => $vehiculo->id,
                'tipo_mantenimiento' => 'CORRECTIVO',
                'descripcion_problema' => 'Descripción actualizada',
                'kilometraje_actual' => 15000,
                'fecha_solicitud' => now()->toDateString(),
            ]);

        $response->assertOk();
        $this->assertSame('Descripción actualizada', $solicitud->fresh()->descripcion_problema);
        $this->assertSame('CORRECTIVO', $solicitud->fresh()->tipo_mantenimiento);
    }

    public function test_no_permite_actualizar_una_solicitud_que_no_esta_pendiente(): void
    {
        $conductor = $this->crearConductor();
        $user = User::factory()->create(['id_persona' => $conductor->id]);
        $user->assignRole('conductor');
        $vehiculo = Vehiculo::factory()->create();
        $this->crearAsignacion($conductor, $vehiculo);
        $this->actingAs($user, 'api');

        // ::create() siempre fuerza estado = PENDIENTE (ver boot() del modelo), por lo
        // que el estado APROBADA se aplica con un update() posterior, que no dispara ese hook.
        $solicitud = $this->crearSolicitud(['id_vehiculo' => $vehiculo->id]);
        $solicitud->update(['estado' => 'APROBADA']);

        $response = $this
            ->putJson(route('api.v1.solicitudes-mantenimiento.update', $solicitud->id), [
                'id_vehiculo' => $vehiculo->id,
                'tipo_mantenimiento' => 'CORRECTIVO',
                'descripcion_problema' => 'No debería aplicarse',
                'kilometraje_actual' => 15000,
                'fecha_solicitud' => now()->toDateString(),
            ]);

        $response->assertStatus(403);
        $this->assertSame('APROBADA', $solicitud->fresh()->estado);
    }

    public function test_update_rechaza_a_usuarios_que_no_son_conductor(): void
    {
        $conductor = $this->crearConductor();
        $autor = User::factory()->create(['id_persona' => $conductor->id]);
        $autor->assignRole('conductor');

        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        $this->actingAs($autor, 'api');
        $solicitud = $this->crearSolicitud();

        $response = $this->actingAs($admin, 'api')
            ->putJson(route('api.v1.solicitudes-mantenimiento.update', $solicitud->id), [
                'id_vehiculo' => $solicitud->id_vehiculo,
                'tipo_mantenimiento' => 'CORRECTIVO',
                'descripcion_problema' => 'No debería aplicarse',
                'fecha_solicitud' => now()->toDateString(),
            ]);

        $response->assertStatus(403);
        $this->assertSame('PENDIENTE', $solicitud->fresh()->estado);
        $this->assertSame('Cambio de aceite', $solicitud->fresh()->descripcion_problema);
    }

    public function test_muestra_el_detalle_de_una_solicitud(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $this->actingAs($admin, 'api');

        $solicitud = $this->crearSolicitud();

        $response = $this
            ->getJson(route('api.v1.solicitudes-mantenimiento.show', $solicitud->id));

        $response->assertOk();
        $response->assertJsonPath('data.id', $solicitud->id);
    }

    private function crearEmisor(string $rol = 'jefe-area'): User
    {
        $emisor = User::factory()->create();
        $emisor->assignRole($rol);

        return $emisor;
    }

    private function crearTecnico(): User
    {
        $tecnico = User::factory()->create(['estado_usuario' => 'ACTIVO']);
        $tecnico->assignRole('tecnico-mantenimiento');

        return $tecnico;
    }

    public function test_emitir_orden_crea_la_orden_heredando_los_datos_de_la_solicitud_y_la_aprueba(): void
    {
        Notification::fake();
        $emisor = $this->crearEmisor();
        $tecnico = $this->crearTecnico();
        $this->actingAs($emisor, 'api');
        $solicitud = $this->crearSolicitud([
            'tipo_mantenimiento' => 'CORRECTIVO',
            'kilometraje_actual' => 85000,
        ]);

        $response = $this->postJson(route('api.v1.solicitudes-mantenimiento.orden-trabajo.store', $solicitud->id), [
            'id_usuario_ejecuta' => $tecnico->id,
            'nota_emisor' => 'Revisar frenos',
            'observacion' => 'Urgente',
            // Datos heredados: si se envían se ignoran.
            'id_vehiculo' => Vehiculo::factory()->create()->id,
            'tipo_mantenimiento' => 'PREVENTIVO',
            'kilometraje_actual' => 1,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.id_solicitud_mantenimiento', $solicitud->id)
            ->assertJsonPath('data.id_vehiculo', $solicitud->id_vehiculo)
            ->assertJsonPath('data.tipo_mantenimiento', 'CORRECTIVO')
            ->assertJsonPath('data.kilometraje_actual', 85000)
            ->assertJsonPath('data.id_usuario_ejecuta', $tecnico->id)
            ->assertJsonPath('data.id_usuario_emite', $emisor->id)
            ->assertJsonPath('data.estado_orden', 'PENDIENTE')
            ->assertJsonPath('data.tipo_orden', 'INTERNO')
            ->assertJsonPath('data.observacion', 'Urgente')
            ->assertJsonPath('data.nota_emisor', 'Revisar frenos');

        $this->assertSame('APROBADA', $solicitud->fresh()->estado);
        $this->assertSame(1, OrdenTrabajo::count());
        Notification::assertSentTo($tecnico, OrdenTrabajoAsignadaNotification::class);
    }

    public function test_emitir_orden_permite_modificar_la_nota_del_emisor_y_asignar_taller(): void
    {
        Notification::fake();
        $tecnico = $this->crearTecnico();
        $this->actingAs($this->crearEmisor('administrador'), 'api');
        $solicitud = $this->crearSolicitud();
        $taller = Taller::create(['razon_social' => 'Taller Central', 'nit' => '123456', 'estado_taller' => 'ACTIVO']);

        $response = $this->postJson(route('api.v1.solicitudes-mantenimiento.orden-trabajo.store', $solicitud->id), [
            'id_usuario_ejecuta' => $tecnico->id,
            'id_taller' => $taller->id,
            'nota_emisor' => 'Revisar también los frenos',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.nota_emisor', 'Revisar también los frenos')
            ->assertJsonPath('data.id_taller', $taller->id)
            ->assertJsonPath('data.tipo_orden', 'EXTERNO');
    }

    public function test_emitir_orden_exige_el_tecnico_y_la_nota_del_emisor(): void
    {
        $tecnico = $this->crearTecnico();
        $this->actingAs($this->crearEmisor(), 'api');
        $solicitud = $this->crearSolicitud();
        $url = route('api.v1.solicitudes-mantenimiento.orden-trabajo.store', $solicitud->id);

        $this->postJson($url, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id_usuario_ejecuta', 'nota_emisor']);

        // Una nota vacía (o sólo espacios) tampoco cuenta como enviada.
        $this->postJson($url, ['id_usuario_ejecuta' => $tecnico->id, 'nota_emisor' => '   '])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nota_emisor');

        $this->assertSame(0, OrdenTrabajo::count());
        $this->assertSame('PENDIENTE', $solicitud->fresh()->estado);
    }

    public function test_emitir_orden_rechaza_un_usuario_que_no_es_tecnico_activo(): void
    {
        $emisor = $this->crearEmisor();
        $inactivo = $this->crearTecnico();
        $inactivo->update(['estado_usuario' => 'INACTIVO']);
        $this->actingAs($emisor, 'api');
        $solicitud = $this->crearSolicitud();

        foreach ([$emisor->id, $inactivo->id] as $idInvalido) {
            $this->postJson(route('api.v1.solicitudes-mantenimiento.orden-trabajo.store', $solicitud->id), [
                'id_usuario_ejecuta' => $idInvalido,
                'nota_emisor' => 'Revisar frenos',
            ])->assertStatus(422)->assertJsonValidationErrors('id_usuario_ejecuta');
        }

        $this->assertSame(0, OrdenTrabajo::count());
    }

    public function test_emitir_orden_rechaza_un_taller_inactivo(): void
    {
        $tecnico = $this->crearTecnico();
        $this->actingAs($this->crearEmisor(), 'api');
        $solicitud = $this->crearSolicitud();
        $taller = Taller::create(['razon_social' => 'Taller Cerrado', 'nit' => '999', 'estado_taller' => 'INACTIVO']);

        $this->postJson(route('api.v1.solicitudes-mantenimiento.orden-trabajo.store', $solicitud->id), [
            'id_usuario_ejecuta' => $tecnico->id,
            'nota_emisor' => 'Revisar frenos',
            'id_taller' => $taller->id,
        ])->assertStatus(422)->assertJsonValidationErrors('id_taller');
    }

    public function test_emitir_orden_rechaza_una_solicitud_que_no_esta_pendiente(): void
    {
        $tecnico = $this->crearTecnico();
        $this->actingAs($this->crearEmisor(), 'api');
        $solicitud = $this->crearSolicitud(['estado' => 'APROBADA']);

        $this->postJson(route('api.v1.solicitudes-mantenimiento.orden-trabajo.store', $solicitud->id), [
            'id_usuario_ejecuta' => $tecnico->id,
            'nota_emisor' => 'Revisar frenos',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Sólo las solicitudes PENDIENTES sin orden de trabajo pueden generar una orden.');

        $this->assertSame(0, OrdenTrabajo::count());
    }

    public function test_emitir_orden_no_permite_una_segunda_orden_para_la_misma_solicitud(): void
    {
        Notification::fake();
        $tecnico = $this->crearTecnico();
        $this->actingAs($this->crearEmisor(), 'api');
        $solicitud = $this->crearSolicitud();
        $payload = ['id_usuario_ejecuta' => $tecnico->id, 'nota_emisor' => 'Revisar frenos'];
        $url = route('api.v1.solicitudes-mantenimiento.orden-trabajo.store', $solicitud->id);

        $this->postJson($url, $payload)->assertCreated();
        $this->postJson($url, $payload)->assertStatus(422);

        $this->assertSame(1, OrdenTrabajo::count());
    }

    public function test_emitir_orden_esta_bloqueado_para_conductores_y_tecnicos(): void
    {
        $solicitud = $this->crearSolicitud();
        $conductor = User::factory()->create();
        $conductor->assignRole('conductor');
        $tecnicoAsignado = $this->crearTecnico();

        foreach ([$conductor, $this->crearTecnico()] as $usuario) {
            $this->actingAs($usuario, 'api')
                ->postJson(route('api.v1.solicitudes-mantenimiento.orden-trabajo.store', $solicitud->id), [
                    'id_usuario_ejecuta' => $tecnicoAsignado->id,
                    'nota_emisor' => 'Revisar frenos',
                ])->assertForbidden();
        }

        $this->assertSame(0, OrdenTrabajo::count());
        $this->assertSame('PENDIENTE', $solicitud->fresh()->estado);
    }

    public function test_emitir_orden_exige_el_permiso_y_no_solo_el_rol(): void
    {
        $tecnico = $this->crearTecnico();
        $solicitud = $this->crearSolicitud();
        $url = route('api.v1.solicitudes-mantenimiento.orden-trabajo.store', $solicitud->id);
        $payload = ['id_usuario_ejecuta' => $tecnico->id, 'nota_emisor' => 'Revisar frenos'];

        // Tiene el rol jefe-area pero se le quitó el permiso: se rechaza.
        $sinPermiso = $this->crearEmisor();
        $sinPermiso->roles()->first()->revokePermissionTo('mantenimiento.ordenes.crear');
        $conPermiso = User::factory()->create();
        $conPermiso->givePermissionTo('mantenimiento.ordenes.crear');

        $this->actingAs($sinPermiso, 'api')->postJson($url, $payload)
            ->assertForbidden()
            ->assertJsonPath('message', 'No tiene permiso para generar órdenes de trabajo.');
        $this->assertSame(0, OrdenTrabajo::count());

        // Un usuario con el permiso asignado directamente (sin rol de gestión) sí puede.
        Notification::fake();
        $this->actingAs($conPermiso, 'api')->postJson($url, $payload)->assertCreated();
    }

    public function test_super_admin_puede_emitir_una_orden_sin_tener_el_permiso_asignado(): void
    {
        Notification::fake();
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superAdmin = $this->crearEmisor('super-admin');
        $tecnico = $this->crearTecnico();
        $solicitud = $this->crearSolicitud();

        $this->actingAs($superAdmin, 'api')
            ->postJson(route('api.v1.solicitudes-mantenimiento.orden-trabajo.store', $solicitud->id), [
                'id_usuario_ejecuta' => $tecnico->id,
                'nota_emisor' => 'Revisar frenos',
            ])->assertCreated();
    }

    public function test_formulario_orden_entrega_la_solicitud_los_valores_por_defecto_y_los_catalogos(): void
    {
        $tecnico = $this->crearTecnico();
        $inactivo = $this->crearTecnico();
        $inactivo->update(['estado_usuario' => 'INACTIVO']);
        Taller::create(['razon_social' => 'Taller Central', 'nit' => '123', 'estado_taller' => 'ACTIVO']);
        Taller::create(['razon_social' => 'Taller Cerrado', 'nit' => '456', 'estado_taller' => 'INACTIVO']);
        $this->actingAs($this->crearEmisor(), 'api');
        $solicitud = $this->crearSolicitud(['tipo_mantenimiento' => 'CORRECTIVO', 'kilometraje_actual' => 85000]);

        $response = $this->getJson(route('api.v1.solicitudes-mantenimiento.orden-trabajo.formulario', $solicitud->id));

        $response->assertOk()
            ->assertJsonPath('data.solicitud.id', $solicitud->id)
            ->assertJsonPath('data.solicitud.tipo_mantenimiento', 'CORRECTIVO')
            ->assertJsonPath('data.solicitud.kilometraje_actual', 85000)
            ->assertJsonPath('data.solicitud.vehiculo.id', $solicitud->id_vehiculo)
            ->assertJsonPath('data.valores_por_defecto.nota_emisor', 'Cambio de aceite');
        $this->assertSame([$tecnico->id], collect($response->json('data.catalogos.tecnicos'))->pluck('id')->all());
        $this->assertSame(['Taller Central'], collect($response->json('data.catalogos.talleres'))->pluck('razon_social')->all());
    }

    public function test_formulario_orden_rechaza_una_solicitud_no_disponible_y_a_quien_no_tiene_permiso(): void
    {
        $conductor = User::factory()->create();
        $conductor->assignRole('conductor');
        $emisor = $this->crearEmisor();
        $this->actingAs($emisor, 'api');
        $aprobada = $this->crearSolicitud(['estado' => 'APROBADA']);
        $pendiente = $this->crearSolicitud();

        $this->getJson(route('api.v1.solicitudes-mantenimiento.orden-trabajo.formulario', $aprobada->id))
            ->assertStatus(422);

        $this->actingAs($conductor, 'api')
            ->getJson(route('api.v1.solicitudes-mantenimiento.orden-trabajo.formulario', $pendiente->id))
            ->assertForbidden();
    }

    public function test_emitir_orden_requiere_autenticacion(): void
    {
        $this->postJson(route('api.v1.solicitudes-mantenimiento.orden-trabajo.store', 1), [])->assertUnauthorized();
    }

    public function test_emitir_orden_responde_404_si_la_solicitud_no_existe(): void
    {
        $tecnico = $this->crearTecnico();
        $this->actingAs($this->crearEmisor(), 'api');

        $this->postJson(route('api.v1.solicitudes-mantenimiento.orden-trabajo.store', 9999), [
            'id_usuario_ejecuta' => $tecnico->id,
            'nota_emisor' => 'Revisar frenos',
        ])->assertNotFound();
    }
}
