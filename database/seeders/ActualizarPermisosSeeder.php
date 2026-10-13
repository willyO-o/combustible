<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Actualiza el ACL de una base de datos que YA está en producción, sin
 * perder datos ni personalizaciones.
 *
 * A diferencia de {@see UserSeeder} y {@see UserSeederInit} (que hacen
 * syncPermissions() y por lo tanto reescriben los permisos de cada rol,
 * borrando lo que se haya ajustado desde el módulo de Roles), este seeder es
 * estrictamente aditivo:
 *
 *  - Crea sólo los permisos de {@see UserSeeder::todosLosPermisos()} que
 *    todavía no existen en la base.
 *  - Esos permisos nuevos se asignan a cada rol cuya definición en
 *    UserSeeder los incluye (givePermissionTo, nunca sync). Los permisos que
 *    ya existían no se tocan: si un administrador quitó uno a un rol, sigue
 *    quitado.
 *  - Si un rol del sistema no existe, se crea con su lista completa.
 *  - No crea, modifica ni elimina usuarios.
 *
 * Ejecutar tras cada despliegue que agregue permisos (es idempotente):
 *   php artisan db:seed --class=Database\\Seeders\\ActualizarPermisosSeeder --force
 */
class ActualizarPermisosSeeder extends UserSeeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $existentes = Permission::where('guard_name', 'web')->pluck('name')->all();
        $nuevos = array_values(array_diff(array_unique($this->todosLosPermisos()), $existentes));

        $this->crearPermisos($nuevos);
        $this->command?->info('Permisos nuevos creados: '.(count($nuevos) ? implode(', ', $nuevos) : 'ninguno'));

        foreach ($this->permisosPorRol() as $nombreRol => $permisosDelRol) {
            $rol = Role::firstOrCreate(['name' => $nombreRol, 'guard_name' => 'web']);

            $permisosAAsignar = $rol->wasRecentlyCreated
                ? array_values(array_unique($permisosDelRol))
                : array_values(array_intersect(array_unique($permisosDelRol), $nuevos));

            if ($permisosAAsignar !== []) {
                $rol->givePermissionTo($permisosAAsignar);
                $this->command?->info("Rol {$nombreRol}: +".count($permisosAAsignar).' permiso(s)');
            }
        }

        $registrar->forgetCachedPermissions();
    }

    /**
     * Definición de permisos por rol, la misma que usan UserSeeder y
     * UserSeederInit.
     *
     * @return array<string, list<string>>
     */
    protected function permisosPorRol(): array
    {
        return [
            'super-admin' => $this->todosLosPermisos(),
            'administrador' => $this->todosLosPermisos(),
            'jefe-area' => $this->permisosParaJefeArea(),
            'conductor' => $this->permisosParaConductor(),
            'tecnico-mantenimiento' => $this->permisosParaTecnicoMantenimiento(),
        ];
    }
}
