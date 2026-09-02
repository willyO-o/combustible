<?php

namespace Database\Seeders;

use Spatie\Permission\PermissionRegistrar;

/**
 * Seeder de arranque para poner el sistema "en blanco" en producción.
 *
 * A diferencia de {@see UserSeeder} (que además crea usuarios de ejemplo
 * vinculados a personas y áreas para el entorno de desarrollo/demo), este
 * seeder:
 *
 *  - Reutiliza EXACTAMENTE el mismo ACL: crea todos los permisos y los cinco
 *    roles del sistema (administrador, super-admin, jefe-area, conductor,
 *    tecnico-mantenimiento) con la misma lista de permisos que UserSeeder,
 *    heredando sus definiciones para que no haya que mantener dos copias.
 *  - Crea únicamente dos usuarios reales, sin `id_persona` (no dependen de
 *    ningún registro de la tabla persona): el super-admin y el administrador.
 *
 * Ejecutar de forma explícita, nunca desde DatabaseSeeder:
 *   php artisan db:seed --class=Database\\Seeders\\UserSeederInit
 *
 * Es idempotente (permisos/roles con firstOrCreate/syncPermissions, usuarios
 * con firstOrCreate): volver a ejecutarlo no duplica nada.
 */
class UserSeederInit extends UserSeeder
{
    /**
     * Contraseña inicial compartida por ambos usuarios de arranque. Debe
     * cambiarse desde el módulo de Usuarios tras el primer inicio de sesión.
     */
    private const PASSWORD_INICIAL = 'PlusMetals#2026Sysmen';

    public function run(): void
    {
        // Mantiene sincronizada la caché de Spatie al ejecutar el seeder.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 1. Todos los permisos del sistema (misma fuente de verdad que UserSeeder).
        $this->crearPermisos($this->todosLosPermisos());

        // 2. Los cinco roles con sus permisos, sin usuarios de ejemplo.
        $superAdmin = $this->crearRolConPermisos('super-admin', $this->todosLosPermisos());
        $administrador = $this->crearRolConPermisos('administrador', $this->todosLosPermisos());
        $this->crearRolConPermisos('jefe-area', $this->permisosParaJefeArea());
        $this->crearRolConPermisos('conductor', $this->permisosParaConductor());
        $this->crearRolConPermisos('tecnico-mantenimiento', $this->permisosParaTecnicoMantenimiento());

        // 3. Únicamente los dos usuarios de arranque, sin id_persona.
        $this->crearUsuarioConRol(
            ['email' => 'wmct2025@gmail.com'],
            [
                'name' => 'Super Administrador',
                'password' => bcrypt(self::PASSWORD_INICIAL),
            ],
            $superAdmin,
        );

        $this->crearUsuarioConRol(
            ['email' => 'solucionesglobalestecnolicas@gmail.com'],
            [
                'name' => 'Administrador',
                'password' => bcrypt(self::PASSWORD_INICIAL),
            ],
            $administrador,
        );
    }
}
