<?php

namespace Tests\Feature\Api\V1;

use App\Models\Conductor;
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
    }

    private function crearConductor(): Conductor
    {
        $persona = Persona::factory()->create();

        return Conductor::create(['id' => $persona->id, 'estado_conductor' => 'ACTIVO']);
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

        $this->crearSolicitud();
        $this->crearSolicitud();

        $response = $this->actingAs($admin, 'api')
            ->getJson(route('api.v1.solicitudes-mantenimiento.index'));

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_un_conductor_solo_ve_sus_propias_solicitudes(): void
    {
        $conductor = $this->crearConductor();
        $user = User::factory()->create(['id_persona' => $conductor->id]);
        $user->assignRole('conductor');

        $this->crearSolicitud(['id_conductor' => $conductor->id]);
        $this->crearSolicitud(); // de otro conductor

        $response = $this->actingAs($user, 'api')
            ->getJson(route('api.v1.solicitudes-mantenimiento.index'));

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_crea_una_solicitud_de_mantenimiento(): void
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

        $response->assertCreated();
        $response->assertJsonPath('data.estado', 'PENDIENTE');
        $response->assertJsonPath('data.id_usuario_registra', $admin->id);
        $this->assertDatabaseHas('solicitud_mantenimiento', [
            'id_vehiculo' => $vehiculo->id,
            'descripcion_problema' => 'Falla en el motor',
            'estado' => 'PENDIENTE',
        ]);
    }

    public function test_una_solicitud_creada_por_un_conductor_se_asocia_automaticamente(): void
    {
        $conductor = $this->crearConductor();
        $user = User::factory()->create(['id_persona' => $conductor->id]);
        $user->assignRole('conductor');
        $vehiculo = Vehiculo::factory()->create();

        $response = $this->actingAs($user, 'api')
            ->postJson(route('api.v1.solicitudes-mantenimiento.store'), [
                'id_vehiculo' => $vehiculo->id,
                'tipo_mantenimiento' => 'PREVENTIVO',
                'descripcion_problema' => 'Mantenimiento programado',
                'fecha_solicitud' => now()->toDateString(),
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.id_conductor', $conductor->id);
    }

    public function test_valida_los_campos_requeridos_al_crear(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        $response = $this->actingAs($admin, 'api')
            ->postJson(route('api.v1.solicitudes-mantenimiento.store'), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['id_vehiculo', 'tipo_mantenimiento', 'descripcion_problema', 'fecha_solicitud']);
    }

    public function test_actualiza_una_solicitud_pendiente(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $solicitud = $this->crearSolicitud();
        $vehiculo = Vehiculo::factory()->create();

        $response = $this->actingAs($admin, 'api')
            ->putJson(route('api.v1.solicitudes-mantenimiento.update', $solicitud->id), [
                'id_vehiculo' => $vehiculo->id,
                'tipo_mantenimiento' => 'CORRECTIVO',
                'descripcion_problema' => 'Descripción actualizada',
                'fecha_solicitud' => now()->toDateString(),
            ]);

        $response->assertOk();
        $this->assertSame('Descripción actualizada', $solicitud->fresh()->descripcion_problema);
        $this->assertSame('CORRECTIVO', $solicitud->fresh()->tipo_mantenimiento);
    }

    public function test_no_permite_actualizar_una_solicitud_que_no_esta_pendiente(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $solicitud = $this->crearSolicitud(['estado' => 'APROBADA']);

        $response = $this->actingAs($admin, 'api')
            ->putJson(route('api.v1.solicitudes-mantenimiento.update', $solicitud->id), [
                'id_vehiculo' => $solicitud->id_vehiculo,
                'tipo_mantenimiento' => 'CORRECTIVO',
                'descripcion_problema' => 'No debería aplicarse',
                'fecha_solicitud' => now()->toDateString(),
            ]);

        $response->assertStatus(403);
        $this->assertSame('APROBADA', $solicitud->fresh()->estado);
    }

    public function test_muestra_el_detalle_de_una_solicitud(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $solicitud = $this->crearSolicitud();

        $response = $this->actingAs($admin, 'api')
            ->getJson(route('api.v1.solicitudes-mantenimiento.show', $solicitud->id));

        $response->assertOk();
        $response->assertJsonPath('data.id', $solicitud->id);
    }
}
