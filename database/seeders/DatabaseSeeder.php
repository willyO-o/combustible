<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Rol;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();


        Rol::updateOrCreate([
            'rol' => 'ADMINISTRADOR',
        ], [
            'estado_rol' => 'ACTIVO',
        ]);
        Rol::updateOrCreate([
            'rol' => 'USUARIO',
        ], [
            'estado_rol' => 'ACTIVO',
        ]);


        $user =User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('admin123'),
        ]);

        $user->roles()->attach(1); // Asignar el rol con ID 1 al usuario recién creado



        // Ejecutar seeders de datos parametricos
        $this->call([
            TipoCombustibleSeeder::class,
            TipoMantenimientoSeeder::class,
            TipoVehiculoSeeder::class,
            ConductorSeeder::class,
            GrifoSeeder::class,
            VehiculoSeeder::class,
        ]);
    }
}
