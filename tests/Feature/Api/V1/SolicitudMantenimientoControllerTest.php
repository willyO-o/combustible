<?php

namespace Tests\Feature\Api\V1;

use App\Models\Asignacion;
use App\Models\Conductor;
use App\Models\ParametrosEmpresa;
use App\Models\Persona;
use App\Models\SolicitudMantenimiento;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SolicitudMantenimientoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);

        // SolicitudMantenimiento::calcularGestion()/getNroAttribute() leen este parámetro.
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
}
