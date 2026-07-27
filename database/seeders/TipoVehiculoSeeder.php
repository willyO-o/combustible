<?php

namespace Database\Seeders;

use App\Models\TipoVehiculo;
use Illuminate\Database\Seeder;

class TipoVehiculoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tiposVehiculos = [
            // --- TRANSPORTE PESADO Y LOGÍSTICA DE MINERALES (Carretera / Kilometraje y Tacógrafo) ---
            [
                'tipo_vehiculo' => 'Tractocamión (Tráiler / Mula)',
                'estado_tipo_vehiculo' => 'ACTIVO',
            ],
            [
                'tipo_vehiculo' => 'Camión de Carga de Alto Tonelaje (Semirremolque / Chata)',
                'estado_tipo_vehiculo' => 'ACTIVO',
            ],
            [
                'tipo_vehiculo' => 'Camión Volquete (Tolva para transporte de carga mineral)',
                'estado_tipo_vehiculo' => 'ACTIVO',
            ],
            [
                'tipo_vehiculo' => 'Camión Cisterna (Transporte de combustible o agua industrial)',
                'estado_tipo_vehiculo' => 'ACTIVO',
            ],

            // --- MAQUINARIA PESADA Y EQUIPOS DE PLANTA / EXTRACCIÓN (Predominio de Horómetro) ---
            [
                'tipo_vehiculo' => 'Cargador Frontal (Operación en cancha de minerales y acopio)',
                'estado_tipo_vehiculo' => 'ACTIVO',
            ],
            [
                'tipo_vehiculo' => 'Excavadora Hidráulica (Trabajos en frente de mina / destape)',
                'estado_tipo_vehiculo' => 'ACTIVO',
            ],
            [
                'tipo_vehiculo' => 'Tractor de Orugas / Bulldozer (Mantenimiento de botaderos y caminos mineros)',
                'estado_tipo_vehiculo' => 'ACTIVO',
            ],
            [
                'tipo_vehiculo' => 'Motoniveladora / Patrol (Mantenimiento de vías de acceso a concesión)',
                'estado_tipo_vehiculo' => 'ACTIVO',
            ],
            [
                'tipo_vehiculo' => 'Retroexcavadora',
                'estado_tipo_vehiculo' => 'ACTIVO',
            ],
            [
                'tipo_vehiculo' => 'Minicargador (Skid Steer)',
                'estado_tipo_vehiculo' => 'ACTIVO',
            ],

            // --- EQUIPOS DE PLANTA DE PROCESAMIENTO Y MANIOBRAS ---
            [
                'tipo_vehiculo' => 'Grúa Horquilla / Montacargas (Manipulación de carga en almacén de concentrados)',
                'estado_tipo_vehiculo' => 'ACTIVO',
            ],
            [
                'tipo_vehiculo' => 'Grúa Móvil Industrial',
                'estado_tipo_vehiculo' => 'ACTIVO',
            ],
            [
                'tipo_vehiculo' => 'Grupo Electrógeno / Planta de Luz Estacionaria o Móvil',
                'estado_tipo_vehiculo' => 'ACTIVO',
            ],
            [
                'tipo_vehiculo' => 'Torre de Iluminación Móvil (Operación nocturna en canchas)',
                'estado_tipo_vehiculo' => 'ACTIVO',
            ],

            // --- VEHÍCULOS LIVIANOS Y TRANSPORTE DE PERSONAL (Operación en Oruro y campamentos) ---
            [
                'tipo_vehiculo' => 'Camioneta 4x4 (Supervisión y operaciones mineras)',
                'estado_tipo_vehiculo' => 'ACTIVO',
            ],
            [
                'tipo_vehiculo' => 'Minibús (Transporte de personal / cuadrillas)',
                'estado_tipo_vehiculo' => 'ACTIVO',
            ],
            [
                'tipo_vehiculo' => 'Automóvil (Uso administrativo / Gerencia)',
                'estado_tipo_vehiculo' => 'ACTIVO',
            ],
            [
                'tipo_vehiculo' => 'Motocicleta (Mensajería y logística rápida local)',
                'estado_tipo_vehiculo' => 'ACTIVO',
            ]
        ];

        foreach ($tiposVehiculos as $tipo) {
            TipoVehiculo::updateOrCreate(
                ['tipo_vehiculo' => $tipo['tipo_vehiculo']],
                ['estado_tipo_vehiculo' => $tipo['estado_tipo_vehiculo']]
            );
        }

        $this->command->info('✓ Tipos de vehículos creados exitosamente');
    }
}
