<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        foreach (['dashboard.ver', 'vales.ver', 'vales.crear', 'roles.ver'] as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');
        $this->actingAs($this->admin);
    }

    public function test_index_esta_bloqueado_para_roles_sin_permiso(): void
    {
        Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);
        $conductor = User::factory()->create();
        $conductor->assignRole('conductor');

        $this->actingAs($conductor)
            ->get(route('roles.index'))
            ->assertForbidden();
    }

    public function test_index_lista_los_roles_sin_incluir_super_admin(): void
    {
        Role::firstOrCreate(['name' => 'jefe-area', 'guard_name' => 'web']);

        $response = $this->get(route('roles.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Roles/Index')
            ->where('roles.data', fn ($roles) => ! collect($roles)->pluck('name')->contains('super-admin'))
        );
    }

    public function test_create_expone_el_catalogo_de_permisos_agrupado(): void
    {
        $response = $this->get(route('roles.create'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Roles/Create')
            ->has('modulos')
        );
    }

    public function test_store_crea_un_rol_con_los_permisos_seleccionados(): void
    {
        $response = $this->post(route('roles.store'), [
            'name' => 'supervisor-taller',
            'permisos' => ['vales.ver', 'vales.crear'],
        ]);

        $response->assertRedirect(route('roles.index'));

        $role = Role::where('name', 'supervisor-taller')->first();
        $this->assertNotNull($role);
        $this->assertEqualsCanonicalizing(['vales.ver', 'vales.crear'], $role->permissions->pluck('name')->all());
    }

    public function test_store_rechaza_el_nombre_reservado_super_admin(): void
    {
        $response = $this->post(route('roles.store'), [
            'name' => 'super-admin',
            'permisos' => [],
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_store_esta_bloqueado_para_quien_no_es_administrador(): void
    {
        Role::firstOrCreate(['name' => 'jefe-area', 'guard_name' => 'web']);
        $jefe = User::factory()->create();
        $jefe->assignRole('jefe-area');

        $response = $this->actingAs($jefe)->post(route('roles.store'), [
            'name' => 'nuevo-rol',
            'permisos' => [],
        ]);

        $response->assertForbidden();
        $this->assertNull(Role::where('name', 'nuevo-rol')->first());
    }

    public function test_edit_devuelve_404_para_el_rol_super_admin(): void
    {
        $superAdmin = Role::where('name', 'super-admin')->first();

        $this->get(route('roles.edit', $superAdmin->id))
            ->assertNotFound();
    }

    public function test_update_agrega_permisos_a_un_rol_no_protegido_y_puede_quitarlos(): void
    {
        $role = Role::firstOrCreate(['name' => 'supervisor-taller', 'guard_name' => 'web']);
        $role->syncPermissions(['vales.ver', 'vales.crear']);

        $response = $this->put(route('roles.update', $role->id), [
            'permisos' => ['vales.ver'],
        ]);

        $response->assertRedirect(route('roles.index'));
        $this->assertEqualsCanonicalizing(['vales.ver'], $role->fresh()->permissions->pluck('name')->all());
    }

    public function test_update_de_un_rol_protegido_no_permite_quitar_sus_permisos_base(): void
    {
        $role = Role::firstOrCreate(['name' => 'conductor', 'guard_name' => 'web']);
        $role->syncPermissions(['vales.ver', 'vales.crear']);

        // Se envía sin "vales.crear" (intentando quitarlo) y con "dashboard.ver" (nuevo).
        $response = $this->put(route('roles.update', $role->id), [
            'permisos' => ['vales.ver', 'dashboard.ver'],
        ]);

        $response->assertRedirect(route('roles.index'));
        $this->assertEqualsCanonicalizing(
            ['vales.ver', 'vales.crear', 'dashboard.ver'],
            $role->fresh()->permissions->pluck('name')->all()
        );
    }

    public function test_update_no_permite_editar_el_rol_super_admin(): void
    {
        $superAdmin = Role::where('name', 'super-admin')->first();

        $this->put(route('roles.update', $superAdmin->id), [
            'permisos' => ['dashboard.ver'],
        ])->assertForbidden();
    }
}
