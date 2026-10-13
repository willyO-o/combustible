<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ActualizarPermisosSeeder;
use Database\Seeders\UserSeederInit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ActualizarPermisosSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Simula una base de producción desplegada antes de que existieran los
     * permisos de exportación de listados.
     */
    private function simularProduccionSinPermisosDeReportes(): void
    {
        $this->seed(UserSeederInit::class);

        Permission::whereIn('name', [
            'vehiculos.reporte.pdf',
            'vehiculos.reporte.excel',
            'personas.reporte.pdf',
        ])->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_crea_los_permisos_faltantes_y_los_asigna_a_los_roles_que_los_definen(): void
    {
        $this->simularProduccionSinPermisosDeReportes();

        $this->seed(ActualizarPermisosSeeder::class);

        $this->assertDatabaseHas('permissions', ['name' => 'vehiculos.reporte.pdf', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'personas.reporte.pdf', 'guard_name' => 'web']);

        $this->assertTrue(Role::findByName('administrador')->hasPermissionTo('vehiculos.reporte.pdf'));
        $this->assertTrue(Role::findByName('administrador')->hasPermissionTo('personas.reporte.pdf'));
        $this->assertTrue(Role::findByName('super-admin')->hasPermissionTo('vehiculos.reporte.excel'));
        // jefe-area recibe los catálogos (vehículos) pero no el módulo personas.
        $this->assertTrue(Role::findByName('jefe-area')->hasPermissionTo('vehiculos.reporte.pdf'));
        $this->assertFalse(Role::findByName('jefe-area')->hasPermissionTo('personas.reporte.pdf'));
        $this->assertFalse(Role::findByName('conductor')->hasPermissionTo('vehiculos.reporte.pdf'));
    }

    public function test_no_devuelve_permisos_existentes_que_se_quitaron_a_un_rol(): void
    {
        $this->simularProduccionSinPermisosDeReportes();

        // Personalización hecha desde el módulo de Roles en producción.
        Role::findByName('jefe-area')->revokePermissionTo('vales.eliminar');

        $this->seed(ActualizarPermisosSeeder::class);

        $this->assertFalse(Role::findByName('jefe-area')->fresh()->hasPermissionTo('vales.eliminar'));
    }

    public function test_conserva_permisos_extra_asignados_a_un_rol(): void
    {
        $this->simularProduccionSinPermisosDeReportes();

        Role::findByName('conductor')->givePermissionTo('vales.crear');

        $this->seed(ActualizarPermisosSeeder::class);

        $this->assertTrue(Role::findByName('conductor')->fresh()->hasPermissionTo('vales.crear'));
    }

    public function test_no_crea_ni_modifica_usuarios(): void
    {
        $this->simularProduccionSinPermisosDeReportes();
        $usuarios = User::count();

        $this->seed(ActualizarPermisosSeeder::class);

        $this->assertSame($usuarios, User::count());
        $this->assertDatabaseMissing('users', ['email' => 'admin@gmail.com']);
    }

    public function test_crea_un_rol_del_sistema_inexistente_con_todos_sus_permisos(): void
    {
        $this->simularProduccionSinPermisosDeReportes();
        Role::findByName('tecnico-mantenimiento')->delete();

        $this->seed(ActualizarPermisosSeeder::class);

        $tecnico = Role::findByName('tecnico-mantenimiento');
        $this->assertTrue($tecnico->hasPermissionTo('mantenimiento.ordenes.ejecucion.registrar'));
        $this->assertFalse($tecnico->hasPermissionTo('operacion-diaria.ver'));
    }

    public function test_es_idempotente(): void
    {
        $this->simularProduccionSinPermisosDeReportes();

        $this->seed(ActualizarPermisosSeeder::class);
        $permisos = Permission::count();
        $asignaciones = Role::findByName('administrador')->permissions()->count();

        $this->seed(ActualizarPermisosSeeder::class);

        $this->assertSame($permisos, Permission::count());
        $this->assertSame($asignaciones, Role::findByName('administrador')->permissions()->count());
    }
}
