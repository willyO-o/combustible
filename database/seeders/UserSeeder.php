<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Mantiene sincronizada la caché de Spatie al ejecutar el seeder.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        /*
         * Convención: "modulo.accion" y "modulo.submodulo.accion".
         * Los permisos se crean de forma idempotente, por lo que puedes ejecutar
         * el seeder varias veces sin duplicarlos. La asignación a roles se realiza
         * por separado.
         */
        $permisos = [
            // Panel principal
            'dashboard.ver',

            // Vales y cargas de combustible
            'vales.ver',
            'vales.crear',
            'vales.editar',
            'vales.eliminar',
            'cargas-combustible.ver',
            'cargas-combustible.registrar',
            'cargas-combustible.editar',
            'cargas-combustible.eliminar',

            // Mantenimiento: solicitud, orden de trabajo y ejecución
            'mantenimiento.solicitudes.ver',
            'mantenimiento.solicitudes.crear',
            'mantenimiento.ordenes.ver',
            'mantenimiento.ordenes.crear',
            'mantenimiento.ordenes.editar',
            'mantenimiento.ordenes.estado.cambiar',
            'mantenimiento.ordenes.ejecucion.registrar',

            // Catálogos
            'conductores.ver',
            'conductores.crear',
            'conductores.editar',
            'conductores.eliminar',
            'vehiculos.ver',
            'vehiculos.crear',
            'vehiculos.editar',
            'vehiculos.eliminar',
            'grifos.ver',
            'grifos.crear',
            'grifos.editar',
            'grifos.eliminar',
            'tipos-combustible.ver',
            'tipos-combustible.crear',
            'tipos-combustible.editar',
            'tipos-combustible.eliminar',
            'tipos-mantenimiento.ver',
            'tipos-mantenimiento.crear',
            'tipos-mantenimiento.editar',
            'tipos-mantenimiento.eliminar',
            'tipos-vehiculo.ver',
            'tipos-vehiculo.crear',
            'tipos-vehiculo.editar',
            'tipos-vehiculo.eliminar',

            // Administración de usuarios
            'usuarios.ver',
            'usuarios.crear',
            'usuarios.editar',
            'usuarios.eliminar',
            'usuarios.contrasena.cambiar',
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate([
                'name' => $permiso,
                'guard_name' => 'web',
            ]);
        }

        $adminRole = Role::firstOrCreate([
            'name' => 'administrador',
            'guard_name' => 'web',
        ]);


        // Asignar todos los permisos al rol de administrador
        $adminRole->syncPermissions(Permission::all());

        // Crear un usuario administrador
        $adminUser = User::firstOrCreate([
            'email' => 'admin@gmail.com',
        ], [
            'name' => 'Admin User',
            'password' => bcrypt('admin123'),
        ]);

        $conductorUser = User::firstOrCreate([
            'email' => 'conductor@gmail.com',
        ], [
            'name' => 'Conductor User',
            'password' => bcrypt('conductor123'),
            'id_persona' => 1, // Asignar el ID de la persona correspondiente
        ]);

        $conductorRole = Role::firstOrCreate([
            'name' => 'conductor',
            'guard_name' => 'web',
        ]);

        $conductorUser->assignRole($conductorRole);

        //asignar los roles             'vales.ver', 'cargas-combustible.ver', 'cargas-combustible.registrar',
        $conductorRole->syncPermissions([
            'vales.ver',
            'cargas-combustible.ver',
            'cargas-combustible.registrar',
        ]);
        // Asignar rol de administrador al usuario administrador
        $adminUser->assignRole($adminRole);



        $superAdminRole = Role::firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => 'web',
        ]);

        $superAdminUser = User::firstOrCreate([
            'email' => 'super-admin@gmail.com',
        ], [
            'name' => 'Super Admin User',
            'password' => bcrypt('superadmin123'),
        ]);

        $superAdminUser->assignRole($superAdminRole);
    }
}
