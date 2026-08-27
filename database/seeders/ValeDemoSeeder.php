<?php

namespace Database\Seeders;

use App\Models\Conductor;
use App\Models\Grifo;
use App\Models\ParametrosEmpresa;
use App\Models\User;
use App\Models\Vale;
use App\Models\Vehiculo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Datos de prueba para ejercitar el scroll infinito del listado mobile de
 * Vales/Index.vue (resources/js/Components/InfiniteScroll, ver
 * .ai/rules/pages.md): con paginate(10) y el filtro por defecto "Este mes",
 * 40 vales repartidos en lo que va del mes dan varias páginas para scrollear.
 * No se registra en DatabaseSeeder::run() porque es data transaccional de
 * prueba, no un catálogo — ejecutar manualmente con:
 *   php artisan db:seed --class=ValeDemoSeeder
 *
 * Requiere que ya existan los seeders base (vehículos, conductores, grifos,
 * parámetros de la empresa, usuarios).
 */
class ValeDemoSeeder extends Seeder
{
    private const CANTIDAD = 40;

    public function run(): void
    {
        $vehiculos = Vehiculo::where('estado_vehiculo', 'ACTIVO')->get();
        $conductoresActivos = Conductor::where('estado_conductor', 'ACTIVO')->get();
        $grifos = Grifo::where('estado_grifo', 'ACTIVO')->get();
        $usuario = User::role('administrador')->first() ?? User::first();
        $parametrosEmpresa = ParametrosEmpresa::first();

        if ($vehiculos->isEmpty() || $conductoresActivos->isEmpty() || $grifos->isEmpty() || ! $usuario || ! $parametrosEmpresa) {
            $this->command->warn('ValeDemoSeeder: faltan datos base (vehículos/conductores/grifos/parámetros de la empresa/usuarios). Ejecuta primero los seeders principales.');

            return;
        }

        $diasExpiracion = $parametrosEmpresa->parametros_vale->tiempo_expiracion;

        // Vale::boot() toma auth()->id() para id_user al crear (mismo
        // criterio que SolicitudMantenimientoDemoSeeder).
        Auth::login($usuario);

        // Distribución realista de estados: la mayoría pendientes (es lo que
        // se ve normalmente en el listado, recién emitidos), algunos ya
        // usados o anulados.
        $estados = array_merge(
            array_fill(0, 28, 'PENDIENTE'),
            array_fill(0, 8, 'USADO'),
            array_fill(0, 4, 'ANULADO'),
        );
        shuffle($estados);

        for ($i = 0; $i < self::CANTIDAD; $i++) {
            /** @var Vehiculo $vehiculo */
            $vehiculo = $vehiculos->random();

            // Prioriza el conductor realmente asignado al vehículo (igual
            // que en Vales/Create.vue); si no tiene ninguno, cualquier
            // conductor activo sirve para el dato de prueba.
            $conductor = $vehiculo->conductoresAsignados->first() ?? $conductoresActivos->random();

            // Repartidos a lo largo de lo que va del mes actual (no todos
            // "ahora mismo") para que el filtro por defecto "Este mes" del
            // listado los muestre todos, con horas variadas.
            $fechaEmision = now()->startOfMonth()
                ->addDays(random_int(0, now()->day - 1))
                ->setTime(random_int(7, 18), random_int(0, 3) * 15);

            $vale = Vale::create([
                'id_vehiculo' => $vehiculo->id,
                'id_conductor' => $conductor->id,
                'id_grifo' => $grifos->random()->id,
                'id_tipo_combustible' => $vehiculo->id_tipo_combustible,
                'litros' => random_int(3000, 15000) / 100, // 30.00 - 150.00 Lt
                'precio' => random_int(650, 920) / 100,    // 6.50 - 9.20 Bs/Lt
                'estado_vale' => $estados[$i],
            ]);

            // Vale::boot() fuerza fecha_emision = now() y calcula
            // fecha_vencimiento en base a eso al crear; se ajustan ambas
            // juntas después (update, no vuelve a disparar ese hook) para que
            // el vencimiento siga siendo coherente con la fecha ya repartida.
            $vale->update([
                'fecha_emision' => $fechaEmision,
                'fecha_vencimiento' => $fechaEmision->copy()->addDays($diasExpiracion),
            ]);
        }

        Auth::logout();

        $this->command->info('✓ '.self::CANTIDAD.' vales de prueba creados exitosamente');
    }
}
