<?php

namespace Database\Seeders;

use App\Models\Vehiculo;
use App\Models\VehiculoArea;
use Illuminate\Database\Seeder;

class VehiculoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vehiculos = [
            // --- TRANSPORTE PESADO Y LOGÍSTICA DE MINERALES (kilometraje) ---
            [
                'nro_placa' => '148-JLK',
                'codigo' => 'PM-TRA-0001',
                'anio' => '2019',
                'marca' => 'Volvo',
                'modelo' => 'FH16',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 3, // Diésel
                'id_tipo_vehiculo' => 1,    // Tractocamión
                'fotografia' => 'vehiculos/CaPy03xlQuLTZhLkBPlZi0QlXmfDKkDpmwjgd5BS.jpg',
                'tipo_medicion' => 'kilometraje',
            ],
            [
                'nro_placa' => '203-MNB',
                'codigo' => 'PM-TRA-0002',
                'anio' => '2018',
                'marca' => 'Scania',
                'modelo' => 'R450',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 3,
                'id_tipo_vehiculo' => 2,    // Camión de Carga Alto Tonelaje
                'fotografia' => 'vehiculos/CaPy03xlQuLTZhLkBPlZi0QlXmfDKkDpmwjgd5BS.jpg',
                'tipo_medicion' => 'kilometraje',
            ],
            [
                'nro_placa' => '317-PQR',
                'codigo' => 'PM-VOL-0001',
                'anio' => '2020',
                'marca' => 'Mercedes-Benz',
                'modelo' => 'Actros 3336',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 3,
                'id_tipo_vehiculo' => 3,    // Camión Volquete
                'fotografia' => 'vehiculos/CaPy03xlQuLTZhLkBPlZi0QlXmfDKkDpmwjgd5BS.jpg',
                'tipo_medicion' => 'kilometraje',
            ],
            [
                'nro_placa' => '412-XYZ',
                'codigo' => 'PM-VOL-0002',
                'anio' => '2021',
                'marca' => 'International',
                'modelo' => '9800i',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 3,
                'id_tipo_vehiculo' => 3,    // Camión Volquete
                'fotografia' => 'vehiculos/CaPy03xlQuLTZhLkBPlZi0QlXmfDKkDpmwjgd5BS.jpg',
                'tipo_medicion' => 'kilometraje',
            ],
            [
                'nro_placa' => '526-CST',
                'codigo' => 'PM-CIS-0001',
                'anio' => '2017',
                'marca' => 'Freightliner',
                'modelo' => 'Cascadia',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 3,
                'id_tipo_vehiculo' => 4,    // Camión Cisterna
                'fotografia' => 'vehiculos/CaPy03xlQuLTZhLkBPlZi0QlXmfDKkDpmwjgd5BS.jpg',
                'tipo_medicion' => 'kilometraje',
            ],

            // --- MAQUINARIA PESADA DE EXTRACCIÓN (horómetro) ---
            [
                'nro_placa' => null, // maquinaria pesada suele no circular con placa vehicular estándar
                'codigo' => 'PM-CAR-0001',
                'anio' => '2016',
                'marca' => 'Caterpillar',
                'modelo' => '950M',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 3,
                'id_tipo_vehiculo' => 5,    // Cargador Frontal
                'fotografia' => 'vehiculos/CaPy03xlQuLTZhLkBPlZi0QlXmfDKkDpmwjgd5BS.jpg',
                'tipo_medicion' => 'horometro',
            ],
            [
                'nro_placa' => null,
                'codigo' => 'PM-EXC-0001',
                'anio' => '2015',
                'marca' => 'Komatsu',
                'modelo' => 'PC200',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 3,
                'id_tipo_vehiculo' => 6,    // Excavadora Hidráulica
                'fotografia' => 'vehiculos/CaPy03xlQuLTZhLkBPlZi0QlXmfDKkDpmwjgd5BS.jpg',
                'tipo_medicion' => 'horometro',
            ],
            [
                'nro_placa' => null,
                'codigo' => 'PM-BUL-0001',
                'anio' => '2014',
                'marca' => 'Caterpillar',
                'modelo' => 'D6T',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 3,
                'id_tipo_vehiculo' => 7,    // Tractor de Orugas / Bulldozer
                'fotografia' => 'vehiculos/CaPy03xlQuLTZhLkBPlZi0QlXmfDKkDpmwjgd5BS.jpg',
                'tipo_medicion' => 'horometro',
            ],
            [
                'nro_placa' => null,
                'codigo' => 'PM-MOT-0001',
                'anio' => '2018',
                'marca' => 'Komatsu',
                'modelo' => 'GD555',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 3,
                'id_tipo_vehiculo' => 8,    // Motoniveladora / Patrol
                'fotografia' => 'vehiculos/CaPy03xlQuLTZhLkBPlZi0QlXmfDKkDpmwjgd5BS.jpg',
                'tipo_medicion' => 'horometro',
            ],
            [
                'nro_placa' => null,
                'codigo' => 'PM-RET-0001',
                'anio' => '2019',
                'marca' => 'JCB',
                'modelo' => '3CX',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 3,
                'id_tipo_vehiculo' => 9,    // Retroexcavadora
                'fotografia' => 'vehiculos/CaPy03xlQuLTZhLkBPlZi0QlXmfDKkDpmwjgd5BS.jpg',
                'tipo_medicion' => 'horometro',
            ],
            [
                'nro_placa' => null,
                'codigo' => 'PM-MIN-0001',
                'anio' => '2021',
                'marca' => 'Bobcat',
                'modelo' => 'S650',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 3,
                'id_tipo_vehiculo' => 10,   // Minicargador
                'fotografia' => 'vehiculos/CaPy03xlQuLTZhLkBPlZi0QlXmfDKkDpmwjgd5BS.jpg',
                'tipo_medicion' => 'horometro',
            ],

            // --- EQUIPOS DE PLANTA Y MANIOBRAS (horómetro) ---
            [
                'nro_placa' => null,
                'codigo' => 'PM-MON-0001',
                'anio' => '2020',
                'marca' => 'Toyota',
                'modelo' => '8FD25',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 3,
                'id_tipo_vehiculo' => 11,   // Grúa Horquilla / Montacargas
                'fotografia' => 'vehiculos/CaPy03xlQuLTZhLkBPlZi0QlXmfDKkDpmwjgd5BS.jpg',
                'tipo_medicion' => 'horometro',
            ],
            [
                'nro_placa' => '634-GRM',
                'codigo' => 'PM-GRU-0001',
                'anio' => '2017',
                'marca' => 'Liebherr',
                'modelo' => 'LTM 1050',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 3,
                'id_tipo_vehiculo' => 12,   // Grúa Móvil Industrial
                'fotografia' => 'vehiculos/CaPy03xlQuLTZhLkBPlZi0QlXmfDKkDpmwjgd5BS.jpg',
                'tipo_medicion' => 'horometro',
            ],
            [
                'nro_placa' => null,
                'codigo' => 'PM-GEN-0001',
                'anio' => '2019',
                'marca' => 'Cummins',
                'modelo' => 'C150D5',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 3,
                'id_tipo_vehiculo' => 13,   // Grupo Electrógeno
                'fotografia' => 'vehiculos/CaPy03xlQuLTZhLkBPlZi0QlXmfDKkDpmwjgd5BS.jpg',
                'tipo_medicion' => 'horometro',
            ],
            [
                'nro_placa' => null,
                'codigo' => 'PM-TOR-0001',
                'anio' => '2020',
                'marca' => 'Generac',
                'modelo' => 'Mobile Light Tower V20',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 3,
                'id_tipo_vehiculo' => 14,   // Torre de Iluminación Móvil
                'fotografia' => 'vehiculos/CaPy03xlQuLTZhLkBPlZi0QlXmfDKkDpmwjgd5BS.jpg',
                'tipo_medicion' => 'horometro',
            ],

            // --- VEHÍCULOS LIVIANOS Y TRANSPORTE DE PERSONAL (kilometraje) ---
            [
                'nro_placa' => '725-HLC',
                'codigo' => 'PM-CAM-0001',
                'anio' => '2022',
                'marca' => 'Toyota',
                'modelo' => 'Hilux 4x4',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 3,
                'id_tipo_vehiculo' => 15,   // Camioneta 4x4
                'fotografia' => 'vehiculos/CaPy03xlQuLTZhLkBPlZi0QlXmfDKkDpmwjgd5BS.jpg',
                'tipo_medicion' => 'kilometraje',
            ],
            [
                'nro_placa' => '812-NFT',
                'codigo' => 'PM-CAM-0002',
                'anio' => '2021',
                'marca' => 'Nissan',
                'modelo' => 'Frontier 4x4',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 3,
                'id_tipo_vehiculo' => 15,   // Camioneta 4x4
                'fotografia' => 'vehiculos/CaPy03xlQuLTZhLkBPlZi0QlXmfDKkDpmwjgd5BS.jpg',
                'tipo_medicion' => 'kilometraje',
            ],
            [
                'nro_placa' => '936-MNB',
                'codigo' => 'PM-MIB-0001',
                'anio' => '2019',
                'marca' => 'Hyundai',
                'modelo' => 'H1 Van',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 3,
                'id_tipo_vehiculo' => 16,   // Minibús
                'fotografia' => 'vehiculos/CaPy03xlQuLTZhLkBPlZi0QlXmfDKkDpmwjgd5BS.jpg',
                'tipo_medicion' => 'kilometraje',
            ],
            [
                'nro_placa' => '048-ADM',
                'codigo' => 'PM-AUT-0001',
                'anio' => '2023',
                'marca' => 'Toyota',
                'modelo' => 'Corolla',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 1, // Gasolina
                'id_tipo_vehiculo' => 17,   // Automóvil
                'fotografia' => 'vehiculos/CaPy03xlQuLTZhLkBPlZi0QlXmfDKkDpmwjgd5BS.jpg',
                'tipo_medicion' => 'kilometraje',
            ],
            [
                'nro_placa' => '159-MTC',
                'codigo' => 'PM-MOC-0001',
                'anio' => '2022',
                'marca' => 'Honda',
                'modelo' => 'XR150L',
                'estado_vehiculo' => 'ACTIVO',
                'id_tipo_combustible' => 1, // Gasolina
                'id_tipo_vehiculo' => 18,   // Motocicleta
                'fotografia' => 'vehiculos/CaPy03xlQuLTZhLkBPlZi0QlXmfDKkDpmwjgd5BS.jpg',
                'tipo_medicion' => 'kilometraje',
            ],
        ];

        foreach ($vehiculos as $vehiculo) {
            $registro = Vehiculo::updateOrCreate(
                ['nro_placa' => $vehiculo['nro_placa']],
                [
                    'anio' => $vehiculo['anio'],
                    'marca' => $vehiculo['marca'],
                    'codigo' => $vehiculo['codigo'],
                    'estado_vehiculo' => $vehiculo['estado_vehiculo'],
                    'id_tipo_combustible' => $vehiculo['id_tipo_combustible'],
                    'id_tipo_vehiculo' => $vehiculo['id_tipo_vehiculo'],
                    'fotografia' => $vehiculo['fotografia'],
                    'tipo_medicion' => $vehiculo['tipo_medicion'],
                ]
            );

            // Asignar el vehículo a un área específica (por ejemplo, área con id 1)
            $idArea = $registro->id % 2 === 0 ? 1 : 2; // Alternar entre área 1 y área 2 para los vehículos

            VehiculoArea::updateOrCreate(
                ['id_vehiculo' => $registro->id, 'id_area' => $idArea],
                ['fecha_asignacion' => now(), 'estado_asignacion' => 'ACTIVO']
            );
        }

        $this->command->info('✓ Vehículos creados exitosamente');
    }
}
