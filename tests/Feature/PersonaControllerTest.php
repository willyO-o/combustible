<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Asignacion;
use App\Models\Persona;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PersonaControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'jefe-area', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');
    }

    public function test_crea_una_persona_sin_usuario(): void
    {
        $response = $this->actingAs($this->admin)->post(route('personas.store'), [
            'ci' => '11111111',
            'nombres' => 'Juan',
            'paterno' => 'Perez',
            'estado_persona' => 'ACTIVO',
            'tipo' => 'personal',
            'crear_usuario' => false,
        ]);

        $response->assertRedirect(route('personas.index'));

        $persona = Persona::where('ci', '11111111')->firstOrFail();
        $this->assertNull($persona->user);
        $this->assertNull($persona->conductor);
    }

    public function test_crea_una_persona_conductor_con_usuario_y_vehiculo_asignado(): void
    {
        $vehiculo = Vehiculo::factory()->create();

        $response = $this->actingAs($this->admin)->post(route('personas.store'), [
            'ci' => '22222222',
            'nombres' => 'Carlos',
            'paterno' => 'Gomez',
            'estado_persona' => 'ACTIVO',
            'tipo' => 'conductor',
            'estado_conductor' => 'ACTIVO',
            'id_vehiculo' => $vehiculo->id,
            'crear_usuario' => true,
            'email' => 'carlos.gomez@example.com',
            'estado_usuario' => 'ACTIVO',
        ]);

        $response->assertRedirect(route('personas.index'));

        $persona = Persona::where('ci', '22222222')->firstOrFail();

        $this->assertNotNull($persona->user);
        $this->assertSame('carlos.gomez@example.com', $persona->user->email);
        $this->assertTrue($persona->user->hasRole('conductor'));
        $this->assertTrue(Hash::check($persona->ci.'#Plusmetals', $persona->user->password));

        $this->assertDatabaseHas('conductor', ['id' => $persona->id, 'estado_conductor' => 'ACTIVO']);
        $this->assertDatabaseHas('asignacion', [
            'id_conductor' => $persona->id,
            'id_vehiculo' => $vehiculo->id,
            'estado_asignacion' => 'ACTIVO',
        ]);
    }

    public function test_crea_una_persona_jefe_de_area_con_encargo(): void
    {
        $area = Area::factory()->create();

        $response = $this->actingAs($this->admin)->post(route('personas.store'), [
            'ci' => '33333333',
            'nombres' => 'Maria',
            'paterno' => 'Lopez',
            'estado_persona' => 'ACTIVO',
            'tipo' => 'jefe-area',
            'id_area' => $area->id,
            'tipo_encargo' => 'TITULAR',
            'crear_usuario' => true,
            'email' => 'maria.lopez@example.com',
            'estado_usuario' => 'ACTIVO',
        ]);

        $response->assertRedirect(route('personas.index'));

        $persona = Persona::where('ci', '33333333')->firstOrFail();

        $this->assertTrue($persona->user->hasRole('jefe-area'));
        $this->assertDatabaseHas('encargado_area', [
            'id_persona' => $persona->id,
            'id_area' => $area->id,
            'estado_encargo' => 'ACTIVO',
        ]);
    }

    public function test_editar_conductor_reasigna_vehiculo(): void
    {
        $vehiculoInicial = Vehiculo::factory()->create();
        $vehiculoNuevo = Vehiculo::factory()->create();

        $this->actingAs($this->admin)->post(route('personas.store'), [
            'ci' => '44444444',
            'nombres' => 'Pedro',
            'paterno' => 'Rojas',
            'estado_persona' => 'ACTIVO',
            'tipo' => 'conductor',
            'estado_conductor' => 'ACTIVO',
            'id_vehiculo' => $vehiculoInicial->id,
            'crear_usuario' => false,
        ]);

        $persona = Persona::where('ci', '44444444')->firstOrFail();

        $this->actingAs($this->admin)->post(route('personas.update', $persona->id), [
            '_method' => 'PUT',
            'ci' => '44444444',
            'nombres' => 'Pedro',
            'paterno' => 'Rojas',
            'estado_persona' => 'ACTIVO',
            'tipo' => 'conductor',
            'estado_conductor' => 'ACTIVO',
            'id_vehiculo' => $vehiculoNuevo->id,
            'crear_usuario' => false,
        ]);

        $this->assertDatabaseHas('asignacion', [
            'id_conductor' => $persona->id,
            'id_vehiculo' => $vehiculoInicial->id,
            'estado_asignacion' => 'REASIGNADO',
        ]);
        $this->assertDatabaseHas('asignacion', [
            'id_conductor' => $persona->id,
            'id_vehiculo' => $vehiculoNuevo->id,
            'estado_asignacion' => 'ACTIVO',
        ]);

        $asignacionAnterior = Asignacion::where('id_vehiculo', $vehiculoInicial->id)->firstOrFail();
        $this->assertNotNull($asignacionAnterior->fecha_culminacion);
    }

    public function test_editar_persona_de_conductor_a_personal_cierra_asignacion_activa(): void
    {
        $vehiculo = Vehiculo::factory()->create();

        $this->actingAs($this->admin)->post(route('personas.store'), [
            'ci' => '55555555',
            'nombres' => 'Ana',
            'paterno' => 'Diaz',
            'estado_persona' => 'ACTIVO',
            'tipo' => 'conductor',
            'estado_conductor' => 'ACTIVO',
            'id_vehiculo' => $vehiculo->id,
            'crear_usuario' => true,
            'email' => 'ana.diaz@example.com',
            'estado_usuario' => 'ACTIVO',
        ]);

        $persona = Persona::where('ci', '55555555')->firstOrFail();

        $this->actingAs($this->admin)->post(route('personas.update', $persona->id), [
            '_method' => 'PUT',
            'ci' => '55555555',
            'nombres' => 'Ana',
            'paterno' => 'Diaz',
            'estado_persona' => 'ACTIVO',
            'tipo' => 'personal',
            'crear_usuario' => true,
            'email' => 'ana.diaz@example.com',
            'estado_usuario' => 'ACTIVO',
        ]);

        $this->assertDatabaseHas('asignacion', [
            'id_conductor' => $persona->id,
            'id_vehiculo' => $vehiculo->id,
            'estado_asignacion' => 'INACTIVO',
        ]);

        $this->assertFalse($persona->user->fresh()->hasRole('conductor'));
    }

    public function test_login_bloqueado_para_usuario_inactivo(): void
    {
        $user = User::factory()->create(['estado_usuario' => 'INACTIVO']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_index_lista_personas_con_jefe_de_area(): void
    {
        $area = Area::factory()->create();

        $this->actingAs($this->admin)->post(route('personas.store'), [
            'ci' => '66666666',
            'nombres' => 'Luis',
            'paterno' => 'Vargas',
            'estado_persona' => 'ACTIVO',
            'tipo' => 'jefe-area',
            'id_area' => $area->id,
            'tipo_encargo' => 'TITULAR',
            'crear_usuario' => false,
        ]);

        $response = $this->actingAs($this->admin)->get(route('personas.index'));

        $response->assertOk();
    }

    public function test_edit_carga_datos_de_jefe_de_area(): void
    {
        $area = Area::factory()->create();

        $this->actingAs($this->admin)->post(route('personas.store'), [
            'ci' => '77777777',
            'nombres' => 'Sofia',
            'paterno' => 'Mamani',
            'estado_persona' => 'ACTIVO',
            'tipo' => 'jefe-area',
            'id_area' => $area->id,
            'tipo_encargo' => 'TITULAR',
            'crear_usuario' => false,
        ]);

        $persona = Persona::where('ci', '77777777')->firstOrFail();

        $response = $this->actingAs($this->admin)->get(route('personas.edit', $persona->id));

        $response->assertOk();
    }
}
