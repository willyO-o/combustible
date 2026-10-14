<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Asignacion;
use App\Models\Cargo;
use App\Models\Conductor;
use App\Models\Persona;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $administrador = Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'jefe-area', 'guard_name' => 'web']);

        // Como en producción: el administrador tiene los permisos del módulo
        // Usuarios (UserSeeder::todosLosPermisos()).
        foreach (['usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'usuarios.eliminar', 'usuarios.contrasena.cambiar'] as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }
        $administrador->givePermissionTo(['usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'usuarios.eliminar', 'usuarios.contrasena.cambiar']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');
    }

    /**
     * Un rol sin permisos del módulo Usuarios (ej. jefe de área) no puede
     * crear, editar ni activar/inactivar usuarios desde el backend, aunque
     * llame a las rutas directamente.
     */
    public function test_sin_permisos_de_usuarios_no_puede_crear_editar_ni_cambiar_estado(): void
    {
        $jefe = User::factory()->create();
        $jefe->assignRole('jefe-area');
        $persona = Persona::factory()->create();
        $otro = User::factory()->create(['email' => 'otro@example.com', 'estado_usuario' => 'ACTIVO']);

        $this->actingAs($jefe)->post(route('usuarios.store'), [
            'id_persona' => $persona->id,
            'email' => 'nuevo@example.com',
            'estado_usuario' => 'ACTIVO',
            'roles' => [],
        ])->assertForbidden();

        $this->actingAs($jefe)->put(route('usuarios.update', $otro->id), [
            'email' => 'cambiado@example.com',
            'estado_usuario' => 'ACTIVO',
            'roles' => [],
        ])->assertForbidden();

        $this->actingAs($jefe)->patch(route('usuarios.estado', $otro->id), [
            'estado_usuario' => 'INACTIVO',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'nuevo@example.com']);
        $this->assertSame('otro@example.com', $otro->fresh()->email);
        $this->assertSame('ACTIVO', $otro->fresh()->estado_usuario);
    }

    public function test_el_permiso_de_cada_accion_es_el_mismo_que_usa_la_vista(): void
    {
        $soloCrear = User::factory()->create();
        $soloCrear->givePermissionTo('usuarios.crear');
        $persona = Persona::factory()->create();
        $otro = User::factory()->create(['estado_usuario' => 'ACTIVO']);

        // Con usuarios.crear puede crear...
        $this->actingAs($soloCrear)->post(route('usuarios.store'), [
            'id_persona' => $persona->id,
            'email' => 'creado@example.com',
            'estado_usuario' => 'ACTIVO',
            'roles' => [],
        ])->assertRedirect(route('usuarios.index'));

        // ...pero no editar (usuarios.editar) ni inactivar (usuarios.eliminar).
        $this->actingAs($soloCrear)->put(route('usuarios.update', $otro->id), [
            'email' => $otro->email,
            'estado_usuario' => 'ACTIVO',
            'roles' => [],
        ])->assertForbidden();

        $this->actingAs($soloCrear)->patch(route('usuarios.estado', $otro->id), [
            'estado_usuario' => 'INACTIVO',
        ])->assertForbidden();

        $this->assertDatabaseHas('users', ['email' => 'creado@example.com']);
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

    /**
     * Super-admin: pasa por Gate::before (crear-usuario / editar-usuario), así
     * estos tests prueban la contraseña inicial y no la configuración de permisos.
     */
    private function superAdmin(): User
    {
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $usuario = User::factory()->create();
        $usuario->assignRole('super-admin');

        return $usuario;
    }

    public function test_la_busqueda_de_personas_devuelve_el_ci_para_prellenar_la_contrasena(): void
    {
        Persona::factory()->create(['ci' => '91000001']);

        $this->actingAs($this->admin)->getJson(route('search.personas-sin-usuario', ['q' => '9100']))
            ->assertOk()
            ->assertJsonPath('0.ci', '91000001');
    }

    public function test_crea_el_usuario_con_la_contrasena_inicial_escrita(): void
    {
        $persona = Persona::factory()->create(['ci' => '91000002']);

        $this->actingAs($this->superAdmin())->post(route('usuarios.store'), [
            'id_persona' => $persona->id,
            'email' => 'nuevo@example.com',
            'estado_usuario' => 'ACTIVO',
            'roles' => [],
            'password' => 'Personal2026',
        ])->assertSessionHasNoErrors()->assertRedirect(route('usuarios.index'));

        $usuario = User::where('email', 'nuevo@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('Personal2026', $usuario->password));
    }

    public function test_crea_el_usuario_con_el_formato_por_defecto_si_no_se_escribe_contrasena(): void
    {
        $persona = Persona::factory()->create(['ci' => '91000003']);

        $this->actingAs($this->superAdmin())->post(route('usuarios.store'), [
            'id_persona' => $persona->id,
            'email' => 'formato@example.com',
            'estado_usuario' => 'ACTIVO',
            'roles' => [],
        ])->assertSessionHasNoErrors();

        $usuario = User::where('email', 'formato@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('91000003#Plusmetals', $usuario->password));
    }

    public function test_rechaza_una_contrasena_inicial_debil(): void
    {
        $persona = Persona::factory()->create();

        $this->actingAs($this->superAdmin())->post(route('usuarios.store'), [
            'id_persona' => $persona->id,
            'email' => 'debil@example.com',
            'estado_usuario' => 'ACTIVO',
            'roles' => [],
            'password' => 'corta1',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'debil@example.com']);
    }

    public function test_editar_un_usuario_nunca_cambia_su_contrasena(): void
    {
        $persona = Persona::factory()->create();
        $usuario = User::factory()->create(['id_persona' => $persona->id, 'email' => 'existente@example.com']);
        $hashAnterior = $usuario->password;

        $this->actingAs($this->superAdmin())->post(route('usuarios.update', $usuario->id), [
            '_method' => 'PUT',
            'email' => 'existente@example.com',
            'estado_usuario' => 'ACTIVO',
            'roles' => [],
            'password' => 'x',
        ])->assertSessionHasNoErrors();

        $this->assertSame($hashAnterior, $usuario->fresh()->password);
    }

    public function test_agregar_el_rol_conductor_a_un_jefe_de_area_no_le_cierra_su_encargo(): void
    {
        $area = Area::factory()->create();
        $persona = Persona::factory()->create();

        $this->actingAs($this->admin)->post(route('usuarios.store'), [
            'id_persona' => $persona->id,
            'email' => 'jefe@example.com',
            'estado_usuario' => 'ACTIVO',
            'roles' => ['jefe-area'],
            'id_area' => $area->id,
            'tipo_encargo' => 'TITULAR',
        ])->assertRedirect(route('usuarios.index'));

        $usuario = User::where('email', 'jefe@example.com')->firstOrFail();

        $this->actingAs($this->admin)->post(route('usuarios.update', $usuario->id), [
            '_method' => 'PUT',
            'email' => 'jefe@example.com',
            'estado_usuario' => 'ACTIVO',
            'roles' => ['jefe-area', 'conductor'],
            'estado_conductor' => 'ACTIVO',
            'id_area' => $area->id,
            'tipo_encargo' => 'TITULAR',
        ])->assertRedirect(route('usuarios.index'));

        $usuario->refresh();
        $this->assertTrue($usuario->hasAllRoles(['jefe-area', 'conductor']));
        $this->assertDatabaseHas('conductor', ['id' => $persona->id]);
        $this->assertDatabaseHas('encargado_area', [
            'id_persona' => $persona->id,
            'id_area' => $area->id,
            'estado_encargo' => 'ACTIVO',
        ]);
    }

    public function test_crear_un_usuario_conductor_y_jefe_de_area_registra_ambos(): void
    {
        $area = Area::factory()->create();
        $persona = Persona::factory()->create();

        $this->actingAs($this->admin)->post(route('usuarios.store'), [
            'id_persona' => $persona->id,
            'email' => 'doble@example.com',
            'estado_usuario' => 'ACTIVO',
            'roles' => ['conductor', 'jefe-area'],
            'estado_conductor' => 'ACTIVO',
            'id_area' => $area->id,
            'tipo_encargo' => 'TITULAR',
        ])->assertRedirect(route('usuarios.index'));

        $this->assertDatabaseHas('conductor', ['id' => $persona->id]);
        $this->assertDatabaseHas('encargado_area', [
            'id_persona' => $persona->id,
            'id_area' => $area->id,
            'estado_encargo' => 'ACTIVO',
        ]);
    }

    public function test_editar_un_usuario_conserva_el_rol_oculto_super_admin(): void
    {
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $persona = Persona::factory()->create();
        $usuario = User::factory()->create(['id_persona' => $persona->id, 'email' => 'root@example.com']);
        $usuario->assignRole(['super-admin', 'administrador']);

        $this->actingAs($this->admin)->post(route('usuarios.update', $usuario->id), [
            '_method' => 'PUT',
            'email' => 'root@example.com',
            'estado_usuario' => 'ACTIVO',
            'roles' => ['administrador'],
        ])->assertRedirect(route('usuarios.index'));

        $this->assertTrue($usuario->fresh()->hasRole('super-admin'));
    }

    public function test_editar_un_super_admin_no_precarga_el_rol_oculto_en_el_formulario(): void
    {
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $persona = Persona::factory()->create();
        $usuario = User::factory()->create(['id_persona' => $persona->id]);
        $usuario->assignRole(['super-admin', 'administrador']);

        $this->actingAs($this->admin)->get(route('usuarios.edit', $usuario->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('rolesAsignados', ['administrador']));
    }

    public function test_editar_usuario_marca_conductor_si_conserva_un_vehiculo_asignado_sin_el_rol(): void
    {
        $persona = Persona::factory()->create();
        $conductor = Conductor::factory()->create(['id' => $persona->id]);
        $usuario = User::factory()->create(['id_persona' => $persona->id]);
        $usuario->assignRole('jefe-area');
        $vehiculo = Vehiculo::factory()->create();
        Asignacion::create([
            'id_vehiculo' => $vehiculo->id,
            'id_conductor' => $conductor->id,
            'fecha_asignacion' => now()->subMonth(),
            'estado_asignacion' => 'ACTIVO',
        ]);

        $this->actingAs($this->admin)->get(route('usuarios.edit', $usuario->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('rolesAsignados', fn ($roles) => collect($roles)->sort()->values()->all() === ['conductor', 'jefe-area']));
    }

    public function test_editar_un_usuario_no_borra_el_cargo_de_su_persona(): void
    {
        $cargo = Cargo::where('cargo', 'Analista')->firstOrFail();
        $persona = Persona::factory()->create(['id_cargo' => $cargo->id]);
        $usuario = User::factory()->create(['id_persona' => $persona->id, 'email' => 'cargo@example.com']);

        $this->actingAs($this->admin)->post(route('usuarios.update', $usuario->id), [
            '_method' => 'PUT',
            'email' => 'cargo@example.com',
            'estado_usuario' => 'ACTIVO',
            'roles' => ['administrador'],
        ])->assertRedirect(route('usuarios.index'));

        $this->assertSame($cargo->id, $persona->fresh()->id_cargo);
    }
}
