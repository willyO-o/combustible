<?php

namespace Tests;

use Database\Seeders\ActualizarPermisosSeeder;

/**
 * Carga los roles del sistema con sus permisos reales (los de UserSeeder, sin
 * usuarios de ejemplo), igual que en producción.
 *
 * Cada ruta web exige su permiso (routes/web.php): un rol creado a mano en un
 * test con Role::firstOrCreate() quedaría sin permisos y recibiría 403 en
 * todo. Con este trait, firstOrCreate() devuelve el rol ya sembrado, así que
 * el test prueba exactamente lo que cada rol puede hacer en el sistema real.
 * Requiere RefreshDatabase (Laravel llama a setUpConPermisosReales() después
 * de preparar la base).
 */
trait ConPermisosReales
{
    protected function setUpConPermisosReales(): void
    {
        $this->seed(ActualizarPermisosSeeder::class);
    }
}
