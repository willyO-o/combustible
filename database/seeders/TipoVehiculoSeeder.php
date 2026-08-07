<?php

namespace Database\Seeders;

use App\Models\TipoVehiculo;
use App\Models\GrupoVehiculo;
use Illuminate\Database\Seeder;

class TipoVehiculoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $grupos = [
            ['grupo_vehiculo' => 'Transporte Pesado y Logística de Minerales', 'estado_grupo_vehiculo' => 'ACTIVO'],
            ['grupo_vehiculo' => 'Maquinaria Pesada de Extracción', 'estado_grupo_vehiculo' => 'ACTIVO'],
            ['grupo_vehiculo' => 'Equipos de Planta y Maniobras', 'estado_grupo_vehiculo' => 'ACTIVO'],
            ['grupo_vehiculo' => 'Vehículos Livianos y Transporte de Personal', 'estado_grupo_vehiculo' => 'ACTIVO'],
        ];

        foreach ($grupos as $grupo) {
            GrupoVehiculo::create($grupo);
        }

        $tiposVehiculos = [
            ['tipo_vehiculo' => 'Tractocamión (Tráiler / Mula)', 'id_grupo_vehiculo' => 1],
            ['tipo_vehiculo' => 'Camión de Carga de Alto Tonelaje (Semirremolque / Chata)', 'id_grupo_vehiculo' => 1],
            ['tipo_vehiculo' => 'Camión Volquete (Tolva para transporte de carga mineral)', 'id_grupo_vehiculo' => 1],
            ['tipo_vehiculo' => 'Camión Cisterna (Transporte de combustible o agua industrial)', 'id_grupo_vehiculo' => 1],

            ['tipo_vehiculo' => 'Cargador Frontal (Operación en cancha de minerales y acopio)', 'id_grupo_vehiculo' => 2],
            ['tipo_vehiculo' => 'Excavadora Hidráulica (Trabajos en frente de mina / destape)', 'id_grupo_vehiculo' => 2],
            ['tipo_vehiculo' => 'Tractor de Orugas / Bulldozer (Mantenimiento de botaderos y caminos mineros)', 'id_grupo_vehiculo' => 2],
            ['tipo_vehiculo' => 'Motoniveladora / Patrol (Mantenimiento de vías de acceso a concesión)', 'id_grupo_vehiculo' => 2],
            ['tipo_vehiculo' => 'Retroexcavadora', 'id_grupo_vehiculo' => 2],
            ['tipo_vehiculo' => 'Minicargador (Skid Steer)', 'id_grupo_vehiculo' => 2],

            ['tipo_vehiculo' => 'Grúa Horquilla / Montacargas (Manipulación de carga en almacén de concentrados)', 'id_grupo_vehiculo' => 3],
            ['tipo_vehiculo' => 'Grúa Móvil Industrial', 'id_grupo_vehiculo' => 3],
            ['tipo_vehiculo' => 'Grupo Electrógeno / Planta de Luz Estacionaria o Móvil', 'id_grupo_vehiculo' => 3],
            ['tipo_vehiculo' => 'Torre de Iluminación Móvil (Operación nocturna en canchas)', 'id_grupo_vehiculo' => 3],

            ['tipo_vehiculo' => 'Camioneta 4x4 (Supervisión y operaciones mineras)', 'id_grupo_vehiculo' => 4],
            ['tipo_vehiculo' => 'Minibús (Transporte de personal / cuadrillas)', 'id_grupo_vehiculo' => 4],
            ['tipo_vehiculo' => 'Automóvil (Uso administrativo / Gerencia)', 'id_grupo_vehiculo' => 4],
            ['tipo_vehiculo' => 'Motocicleta (Mensajería y logística rápida local)', 'id_grupo_vehiculo' => 4],
        ];

        foreach ($tiposVehiculos as $item) {
            TipoVehiculo::create([
                'tipo_vehiculo' => $item['tipo_vehiculo'],
                'estado_tipo_vehiculo' => 'ACTIVO',
                'id_grupo_vehiculo' => $item['id_grupo_vehiculo'],
            ]);
        }

        $this->command->info('✓ Tipos de vehículos creados exitosamente');
    }
}
