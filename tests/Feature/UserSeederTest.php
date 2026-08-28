<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Persona;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    private function ejecutarSeeder(): void
    {
        // El seeder crea usuarios de ejemplo vinculados a personas 1-7 y
        // encargados de áreas 1-3.
        Persona::factory()->count(7)->create();
        Area::factory()->count(3)->create();

        $this->seed(UserSeeder::class);
    }

    public function test_el_rol_tecnico_mantenimiento_solo_gestiona_ordenes_de_trabajo(): void
    {
        $this->ejecutarSeeder();

        $permisos = Role::findByName('tecnico-mantenimiento', 'web')
            ->permissions
            ->pluck('name');

        $this->assertEqualsCanonicalizing([
            'dashboard.ver',
            'dashboard.grafico-ordenes.ver',
            'mantenimiento.ordenes.ver',
            'mantenimiento.ordenes.estado.cambiar',
            'mantenimiento.ordenes.ejecucion.registrar',
        ], $permisos->all());
    }

    public function test_todos_los_roles_tienen_acceso_al_dashboard(): void
    {
        $this->ejecutarSeeder();

        $roles = ['administrador', 'super-admin', 'conductor', 'jefe-area', 'tecnico-mantenimiento'];

        foreach ($roles as $rol) {
            $this->assertTrue(
                Role::findByName($rol, 'web')->hasPermissionTo('dashboard.ver'),
                "El rol {$rol} debería tener el permiso dashboard.ver",
            );
        }
    }

    public function test_los_widgets_del_dashboard_los_reciben_solo_admin_super_admin_y_jefe_area(): void
    {
        $this->ejecutarSeeder();

        $widgets = [
            'dashboard.tarjeta-cargas.ver',
            'dashboard.tarjeta-vales.ver',
            'dashboard.tarjeta-vehiculos.ver',
            'dashboard.tarjeta-conductores.ver',
            'dashboard.grafico-combustible.ver',
        ];

        foreach (['administrador', 'super-admin', 'jefe-area'] as $rol) {
            foreach ($widgets as $widget) {
                $this->assertTrue(
                    Role::findByName($rol, 'web')->hasPermissionTo($widget),
                    "El rol {$rol} debería tener el permiso {$widget}",
                );
            }
        }

        foreach (['conductor', 'tecnico-mantenimiento'] as $rol) {
            $permisos = Role::findByName($rol, 'web')->permissions->pluck('name');
            $this->assertEmpty(
                $permisos->filter(fn (string $permiso) => str_starts_with($permiso, 'dashboard.tarjeta-')
                    || $permiso === 'dashboard.grafico-combustible.ver'),
                "El rol {$rol} no debería tener permisos de widgets del dashboard",
            );
        }
    }

    public function test_el_grafico_de_ordenes_lo_reciben_tecnico_mantenimiento_y_admin_pero_no_jefe_area(): void
    {
        $this->ejecutarSeeder();

        foreach (['tecnico-mantenimiento', 'administrador', 'super-admin'] as $rol) {
            $this->assertTrue(
                Role::findByName($rol, 'web')->hasPermissionTo('dashboard.grafico-ordenes.ver'),
                "El rol {$rol} debería tener dashboard.grafico-ordenes.ver",
            );
        }

        foreach (['jefe-area', 'conductor'] as $rol) {
            $this->assertFalse(
                Role::findByName($rol, 'web')->hasPermissionTo('dashboard.grafico-ordenes.ver'),
                "El rol {$rol} no debería tener dashboard.grafico-ordenes.ver",
            );
        }
    }

    public function test_el_grafico_de_horas_trabajadas_lo_reciben_conductor_y_admin_pero_no_jefe_area(): void
    {
        $this->ejecutarSeeder();

        foreach (['conductor', 'administrador', 'super-admin'] as $rol) {
            $this->assertTrue(
                Role::findByName($rol, 'web')->hasPermissionTo('dashboard.grafico-horas.ver'),
                "El rol {$rol} debería tener dashboard.grafico-horas.ver",
            );
        }

        foreach (['jefe-area', 'tecnico-mantenimiento'] as $rol) {
            $this->assertFalse(
                Role::findByName($rol, 'web')->hasPermissionTo('dashboard.grafico-horas.ver'),
                "El rol {$rol} no debería tener dashboard.grafico-horas.ver",
            );
        }
    }

    public function test_el_rol_tecnico_mantenimiento_no_tiene_acceso_a_operacion_diaria(): void
    {
        $this->ejecutarSeeder();

        $permisos = Role::findByName('tecnico-mantenimiento', 'web')
            ->permissions
            ->pluck('name');

        $this->assertEmpty(
            $permisos->filter(fn (string $permiso) => str_starts_with($permiso, 'operacion-diaria.')),
        );
    }
}
