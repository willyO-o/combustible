<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserSeederInit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserSeederInitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function permisosEsperados(): array
    {
        $metodo = new ReflectionMethod(UserSeederInit::class, 'todosLosPermisos');
        $metodo->setAccessible(true);

        return array_values(array_unique($metodo->invoke(new UserSeederInit)));
    }

    public function test_crea_unicamente_los_dos_usuarios_de_arranque_sin_persona(): void
    {
        $this->seed(UserSeederInit::class);

        $this->assertSame(2, User::count());

        $superAdmin = User::where('email', 'wmct2025@gmail.com')->first();
        $administrador = User::where('email', 'solucionesglobalestecnolicas@gmail.com')->first();

        $this->assertNotNull($superAdmin);
        $this->assertNotNull($administrador);

        $this->assertNull($superAdmin->id_persona);
        $this->assertNull($administrador->id_persona);

        $this->assertTrue($superAdmin->hasRole('super-admin'));
        $this->assertTrue($administrador->hasRole('administrador'));
    }

    public function test_las_credenciales_de_arranque_son_validas(): void
    {
        $this->seed(UserSeederInit::class);

        foreach (['wmct2025@gmail.com', 'solucionesglobalestecnolicas@gmail.com'] as $email) {
            $this->assertTrue(
                auth()->attempt(['email' => $email, 'password' => 'PlusMetals#2026Sysmen']),
                "Las credenciales de {$email} deberían ser válidas",
            );
            auth()->logout();
        }
    }

    public function test_migra_todos_los_roles_del_sistema(): void
    {
        $this->seed(UserSeederInit::class);

        $this->assertEqualsCanonicalizing(
            ['super-admin', 'administrador', 'jefe-area', 'conductor', 'tecnico-mantenimiento'],
            Role::pluck('name')->all(),
        );
    }

    public function test_migra_todos_los_permisos_del_acl(): void
    {
        $this->seed(UserSeederInit::class);

        $esperados = $this->permisosEsperados();
        $creados = Permission::pluck('name')->all();

        $this->assertEqualsCanonicalizing($esperados, $creados);
    }

    public function test_administrador_y_super_admin_reciben_todos_los_permisos(): void
    {
        $this->seed(UserSeederInit::class);

        $total = Permission::count();

        foreach (['administrador', 'super-admin'] as $rol) {
            $this->assertSame(
                $total,
                Role::findByName($rol, 'web')->permissions()->count(),
                "El rol {$rol} debería tener todos los permisos",
            );
        }
    }

    public function test_los_roles_operativos_conservan_sus_permisos(): void
    {
        $this->seed(UserSeederInit::class);

        foreach (['jefe-area', 'conductor', 'tecnico-mantenimiento'] as $rol) {
            $this->assertTrue(
                Role::findByName($rol, 'web')->hasPermissionTo('dashboard.ver'),
                "El rol {$rol} debería conservar sus permisos base",
            );
        }
    }

    public function test_es_idempotente(): void
    {
        $this->seed(UserSeederInit::class);
        $this->seed(UserSeederInit::class);

        $this->assertSame(2, User::count());
        $this->assertSame(5, Role::count());
        $this->assertSame(count($this->permisosEsperados()), Permission::count());
    }
}
