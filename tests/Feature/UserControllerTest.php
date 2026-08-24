<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Persona;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserControllerTest extends TestCase
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

    public function test_no_existe_ruta_de_eliminar_usuario(): void
    {
        $this->assertFalse(Route::has('usuarios.destroy'));
    }

    public function test_crea_usuario_conductor_para_persona_existente(): void
    {
        $persona = Persona::factory()->create();
        $vehiculo = Vehiculo::factory()->create();

        $response = $this->actingAs($this->admin)->post(route('usuarios.store'), [
            'id_persona' => $persona->id,
            'email' => 'nuevo.conductor@example.com',
            'estado_usuario' => 'ACTIVO',
            'roles' => ['conductor'],
            'estado_conductor' => 'ACTIVO',
            'id_vehiculo' => $vehiculo->id,
        ]);

        $response->assertRedirect(route('usuarios.index'));

        $usuario = User::where('email', 'nuevo.conductor@example.com')->firstOrFail();
        $this->assertSame($persona->id, $usuario->id_persona);
        $this->assertTrue($usuario->hasRole('conductor'));
        $this->assertDatabaseHas('conductor', ['id' => $persona->id]);
        $this->assertDatabaseHas('asignacion', [
            'id_conductor' => $persona->id,
            'id_vehiculo' => $vehiculo->id,
            'estado_asignacion' => 'ACTIVO',
        ]);
    }

    public function test_no_permite_crear_usuario_para_persona_que_ya_tiene_uno(): void
    {
        $persona = Persona::factory()->create();
        User::factory()->create(['id_persona' => $persona->id]);

        $response = $this->actingAs($this->admin)->post(route('usuarios.store'), [
            'id_persona' => $persona->id,
            'email' => 'duplicado@example.com',
            'estado_usuario' => 'ACTIVO',
            'roles' => [],
        ]);

        $response->assertSessionHasErrors('id_persona');
    }

    public function test_editar_usuario_cambia_de_conductor_a_jefe_de_area(): void
    {
        $persona = Persona::factory()->create();
        $vehiculo = Vehiculo::factory()->create();
        $area = Area::factory()->create();

        $this->actingAs($this->admin)->post(route('usuarios.store'), [
            'id_persona' => $persona->id,
            'email' => 'transicion@example.com',
            'estado_usuario' => 'ACTIVO',
            'roles' => ['conductor'],
            'estado_conductor' => 'ACTIVO',
            'id_vehiculo' => $vehiculo->id,
        ]);

        $usuario = User::where('email', 'transicion@example.com')->firstOrFail();

        $response = $this->actingAs($this->admin)->post(route('usuarios.update', $usuario->id), [
            '_method' => 'PUT',
            'email' => 'transicion@example.com',
            'estado_usuario' => 'ACTIVO',
            'roles' => ['jefe-area'],
            'id_area' => $area->id,
            'tipo_encargo' => 'TITULAR',
        ]);

        $response->assertRedirect(route('usuarios.index'));

        $this->assertTrue($usuario->fresh()->hasRole('jefe-area'));
        $this->assertFalse($usuario->fresh()->hasRole('conductor'));
        $this->assertDatabaseHas('asignacion', [
            'id_conductor' => $persona->id,
            'id_vehiculo' => $vehiculo->id,
            'estado_asignacion' => 'INACTIVO',
        ]);
        $this->assertDatabaseHas('encargado_area', [
            'id_persona' => $persona->id,
            'id_area' => $area->id,
            'estado_encargo' => 'ACTIVO',
        ]);
    }

    public function test_usuario_puede_tener_varios_roles_a_la_vez(): void
    {
        $persona = Persona::factory()->create();
        $vehiculo = Vehiculo::factory()->create();

        $response = $this->actingAs($this->admin)->post(route('usuarios.store'), [
            'id_persona' => $persona->id,
            'email' => 'multirol@example.com',
            'estado_usuario' => 'ACTIVO',
            'roles' => ['conductor', 'administrador'],
            'estado_conductor' => 'ACTIVO',
            'id_vehiculo' => $vehiculo->id,
        ]);

        $response->assertRedirect(route('usuarios.index'));

        $usuario = User::where('email', 'multirol@example.com')->firstOrFail();
        $this->assertTrue($usuario->hasRole('conductor'));
        $this->assertTrue($usuario->hasRole('administrador'));
        $this->assertDatabaseHas('conductor', ['id' => $persona->id]);

        $response = $this->actingAs($this->admin)->post(route('usuarios.update', $usuario->id), [
            '_method' => 'PUT',
            'email' => 'multirol@example.com',
            'estado_usuario' => 'ACTIVO',
            'roles' => ['administrador'],
        ]);

        $response->assertRedirect(route('usuarios.index'));

        $usuario->refresh();
        $this->assertFalse($usuario->hasRole('conductor'));
        $this->assertTrue($usuario->hasRole('administrador'));
        $this->assertDatabaseHas('asignacion', [
            'id_conductor' => $persona->id,
            'id_vehiculo' => $vehiculo->id,
            'estado_asignacion' => 'INACTIVO',
        ]);
    }

    public function test_inactivar_usuario_desde_el_listado(): void
    {
        $persona = Persona::factory()->create();
        $usuario = User::factory()->create(['id_persona' => $persona->id, 'estado_usuario' => 'ACTIVO']);

        $response = $this->actingAs($this->admin)->patch(route('usuarios.estado', $usuario->id), [
            'estado_usuario' => 'INACTIVO',
        ]);

        $response->assertRedirect(route('usuarios.index'));
        $this->assertSame('INACTIVO', $usuario->fresh()->estado_usuario);
    }

    public function test_no_puede_inactivarse_a_si_mismo(): void
    {
        $response = $this->actingAs($this->admin)->patch(route('usuarios.estado', $this->admin->id), [
            'estado_usuario' => 'INACTIVO',
        ]);

        $response->assertRedirect(route('usuarios.index'));
        $this->assertSame('ACTIVO', $this->admin->fresh()->estado_usuario);
    }

    public function test_busqueda_de_personas_sin_usuario_excluye_las_que_ya_tienen(): void
    {
        $sinUsuario = Persona::factory()->create(['ci' => '90000001', 'nombres' => 'Disponible']);
        $conUsuario = Persona::factory()->create(['ci' => '90000002', 'nombres' => 'Ocupada']);
        User::factory()->create(['id_persona' => $conUsuario->id]);

        $response = $this->actingAs($this->admin)->getJson(route('search.personas-sin-usuario', ['q' => '9000']));

        $response->assertOk();
        $ids = collect($response->json())->pluck('id');
        $this->assertTrue($ids->contains($sinUsuario->id));
        $this->assertFalse($ids->contains($conUsuario->id));
    }
}
