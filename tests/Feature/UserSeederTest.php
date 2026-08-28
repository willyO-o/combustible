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
