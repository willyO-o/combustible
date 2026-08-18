<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\IntervaloMantenimientoTipo;
use App\Models\TipoVehiculo;
use App\Models\TipoMantenimiento;

class IntervaloMantenimientoTipoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */


    public function run(): void
    {
        // ── 1. Mapeo de nombre -> id (evita depender del orden de inserción de otros seeders) ──
        $vehiculos = TipoVehiculo::pluck('id', 'tipo_vehiculo');
        $mantenimientos = TipoMantenimiento::pluck('id', 'tipo_mantenimiento');

        // ── 2. Definición de combinaciones: vehículo => [ [mantenimiento, medicion, frecuencia], ... ] ──
        // Fuentes de referencia: manuales Caterpillar/Komatsu/JCB (maquinaria, horómetro) y
        // guías de mantenimiento de flotas de camiones/tractocamiones (kilometraje).
        $data = [

            // ============================================================
            // GRUPO 1 · TRANSPORTE PESADO Y LOGÍSTICA DE MINERALES (KM)
            // ============================================================
            'Tractocamión (Tráiler / Mula)' => [
                ['Cambio de aceite', 'kilometraje', 15000],
                ['Cambio de filtros', 'kilometraje', 15000],
                ['Revisión de frenos', 'kilometraje', 20000],
                ['Revisión de sistema de frenos neumáticos', 'kilometraje', 20000],
                ['Cambio de neumáticos', 'kilometraje', 60000],
                ['Alineación y balanceo', 'kilometraje', 20000],
                ['Revisión de batería', 'kilometraje', 10000],
                ['Revisión de quinta rueda', 'kilometraje', 20000],
                ['Revisión de suspensión neumática', 'kilometraje', 20000],
                ['Cambio de líquido de frenos', 'kilometraje', 40000],
                ['Cambio de aceite de transmisión', 'kilometraje', 80000],
                ['Revisión de sistema de dirección', 'kilometraje', 20000],
                ['Revisión de acoplamiento (king pin / perno rey)', 'kilometraje', 20000],
                ['Revisión de mangueras y conexiones neumáticas', 'kilometraje', 20000],
                ['Inspección técnico-mecánica (revisión técnica)', 'kilometraje', 20000],
            ],

            'Camión de Carga de Alto Tonelaje (Semirremolque / Chata)' => [
                ['Cambio de aceite', 'kilometraje', 15000],
                ['Cambio de filtros', 'kilometraje', 15000],
                ['Revisión de frenos', 'kilometraje', 20000],
                ['Revisión de sistema de frenos neumáticos', 'kilometraje', 20000],
                ['Cambio de neumáticos', 'kilometraje', 50000],
                ['Alineación y balanceo', 'kilometraje', 20000],
                ['Revisión de batería', 'kilometraje', 10000],
                ['Cambio de líquido de frenos', 'kilometraje', 40000],
                ['Cambio de aceite de transmisión', 'kilometraje', 80000],
                ['Revisión de sistema de dirección', 'kilometraje', 20000],
                ['Revisión de suspensión neumática', 'kilometraje', 20000],
                ['Revisión de mangueras y conexiones neumáticas', 'kilometraje', 20000],
                ['Inspección técnico-mecánica (revisión técnica)', 'kilometraje', 20000],
            ],

            'Camión Volquete (Tolva para transporte de carga mineral)' => [
                // Servicio severo (fuera de ruta / cargas pesadas) → intervalos más cortos
                ['Cambio de aceite', 'kilometraje', 10000],
                ['Cambio de filtros', 'kilometraje', 10000],
                ['Revisión de frenos', 'kilometraje', 15000],
                ['Revisión de sistema de frenos neumáticos', 'kilometraje', 15000],
                ['Cambio de neumáticos', 'kilometraje', 30000],
                ['Revisión de suspensión neumática', 'kilometraje', 15000],
                ['Revisión de sistema hidráulico', 'kilometraje', 15000],
                ['Cambio de aceite hidráulico', 'kilometraje', 30000],
                ['Revisión de cilindros hidráulicos', 'kilometraje', 15000],
                ['Revisión de estructura y chasis', 'kilometraje', 20000],
                ['Engrase general de puntos de lubricación', 'kilometraje', 5000],
                ['Revisión de sistema de dirección', 'kilometraje', 15000],
            ],

            'Camión Cisterna (Transporte de combustible o agua industrial)' => [
                ['Cambio de aceite', 'kilometraje', 12000],
                ['Cambio de filtros', 'kilometraje', 12000],
                ['Revisión de frenos', 'kilometraje', 20000],
                ['Revisión de sistema de frenos neumáticos', 'kilometraje', 20000],
                ['Cambio de neumáticos', 'kilometraje', 40000],
                ['Revisión de tanque cisterna', 'kilometraje', 20000],
                ['Calibración de válvulas de descarga', 'kilometraje', 10000],
                ['Prueba de fugas en sistema de gas/combustible', 'kilometraje', 10000],
                ['Revisión de mangueras y conexiones neumáticas', 'kilometraje', 20000],
                ['Certificación de seguridad y operatividad', 'kilometraje', 12000],
                ['Revisión de sistema eléctrico', 'kilometraje', 20000],
                ['Revisión de sistema de dirección', 'kilometraje', 20000],
            ],

            // ============================================================
            // GRUPO 2 · MAQUINARIA PESADA DE EXTRACCIÓN (HORÓMETRO)
            // ============================================================
            'Cargador Frontal (Operación en cancha de minerales y acopio)' => [
                ['Cambio de aceite', 'horometro', 250],
                ['Cambio de filtros', 'horometro', 250],
                ['Engrase general de puntos de lubricación', 'horometro', 100],
                ['Revisión de sistema hidráulico', 'horometro', 250],
                ['Cambio de aceite hidráulico', 'horometro', 2000],
                ['Cambio de filtros hidráulicos', 'horometro', 500],
                ['Revisión de transmisión', 'horometro', 500],
                ['Cambio de aceite de transmisión', 'horometro', 2000],
                ['Revisión de sistema de enfriamiento del motor', 'horometro', 500],
                ['Revisión de cucharón / balde', 'horometro', 250],
                ['Revisión de frenos', 'horometro', 500],
                ['Cambio de neumáticos', 'horometro', 2000],
                ['Revisión de sistema de dirección', 'horometro', 500], // Chasis articulado, sí tiene dirección
            ],

            'Excavadora Hidráulica (Trabajos en frente de mina / destape)' => [
                ['Cambio de aceite', 'horometro', 250],
                ['Cambio de filtros', 'horometro', 250],
                ['Engrase general de puntos de lubricación', 'horometro', 100],
                ['Revisión de sistema hidráulico', 'horometro', 250],
                ['Cambio de aceite hidráulico', 'horometro', 2000],
                ['Cambio de filtros hidráulicos', 'horometro', 500],
                ['Revisión de tren de rodaje / orugas', 'horometro', 500],
                ['Revisión de cilindros hidráulicos', 'horometro', 500],
                ['Revisión de mangueras hidráulicas', 'horometro', 500],
                ['Revisión de cucharón / balde', 'horometro', 250],
                ['Revisión de sistema de enfriamiento del motor', 'horometro', 500],
                ['Revisión de estructura y chasis', 'horometro', 1000],
            ],

            'Tractor de Orugas / Bulldozer (Mantenimiento de botaderos y caminos mineros)' => [
                ['Cambio de aceite', 'horometro', 250],
                ['Cambio de filtros', 'horometro', 250],
                ['Engrase general de puntos de lubricación', 'horometro', 100],
                ['Revisión de tren de rodaje / orugas', 'horometro', 250],
                ['Cambio de aceite hidráulico', 'horometro', 2000],
                ['Cambio de filtros hidráulicos', 'horometro', 500],
                ['Revisión de sistema hidráulico', 'horometro', 250],
                ['Cambio de aceite de transmisión', 'horometro', 1000],
                ['Revisión de transmisión', 'horometro', 500],
                ['Revisión de estructura y chasis', 'horometro', 1000],
                ['Revisión de sistema de enfriamiento del motor', 'horometro', 500],
                ['Revisión de rodamientos y bocinas', 'horometro', 500],
            ],

            'Motoniveladora / Patrol (Mantenimiento de vías de acceso a concesión)' => [
                ['Cambio de aceite', 'horometro', 250],
                ['Cambio de filtros', 'horometro', 250],
                ['Engrase general de puntos de lubricación', 'horometro', 100],
                ['Revisión de sistema hidráulico', 'horometro', 250],
                ['Cambio de aceite hidráulico', 'horometro', 2000],
                ['Revisión de transmisión', 'horometro', 500],
                ['Revisión de sistema de dirección', 'horometro', 250],
                ['Revisión de sistema de enfriamiento del motor', 'horometro', 500],
                ['Revisión de frenos', 'horometro', 500],
                ['Revisión de estructura y chasis', 'horometro', 1000],
                ['Cambio de neumáticos', 'horometro', 2500], // Va sobre ruedas grandes, no orugas
            ],

            'Retroexcavadora' => [
                ['Cambio de aceite', 'horometro', 250],
                ['Cambio de filtros', 'horometro', 250],
                ['Engrase general de puntos de lubricación', 'horometro', 100],
                ['Revisión de sistema hidráulico', 'horometro', 250],
                ['Cambio de aceite hidráulico', 'horometro', 1000],
                ['Cambio de filtros hidráulicos', 'horometro', 500],
                ['Revisión de cucharón / balde', 'horometro', 250],
                ['Revisión de cilindros hidráulicos', 'horometro', 500],
                ['Revisión de transmisión', 'horometro', 500],
                ['Revisión de frenos', 'horometro', 500],
                ['Cambio de neumáticos', 'horometro', 2000],
                ['Revisión de sistema de dirección', 'horometro', 500], // Es de tipo "loader", tiene volante
            ],

            'Minicargador (Skid Steer)' => [
                // Se asume versión sobre ruedas (la más común); la versión de orugas
                // sería un tipo de vehículo distinto ("Minicargador de Orugas / CTL")
                ['Cambio de aceite', 'horometro', 250],
                ['Cambio de filtros', 'horometro', 250],
                ['Engrase general de puntos de lubricación', 'horometro', 100],
                ['Revisión de sistema hidráulico', 'horometro', 250],
                ['Cambio de aceite hidráulico', 'horometro', 1000],
                ['Cambio de filtros hidráulicos', 'horometro', 500],
                ['Revisión de cucharón / balde', 'horometro', 250],
                ['Cambio de neumáticos', 'horometro', 1500],
                ['Revisión de frenos', 'horometro', 500],
            ],

            // ============================================================
            // GRUPO 3 · EQUIPOS DE PLANTA Y MANIOBRAS (HORÓMETRO)
            // ============================================================
            'Grúa Horquilla / Montacargas (Manipulación de carga en almacén de concentrados)' => [
                ['Cambio de aceite', 'horometro', 250],
                ['Cambio de filtros', 'horometro', 250],
                ['Revisión de sistema hidráulico', 'horometro', 250],
                ['Cambio de aceite hidráulico', 'horometro', 1000],
                ['Revisión de mástil y cadenas', 'horometro', 250],
                ['Revisión de horquillas', 'horometro', 250],
                ['Revisión y carga de batería (equipos eléctricos)', 'horometro', 250],
                ['Revisión de frenos', 'horometro', 250],
                ['Cambio de neumáticos', 'horometro', 1000],
            ],

            'Grúa Móvil Industrial' => [
                // Se asume grúa montada sobre camión/chasis rodante (la más común en planta/mina)
                ['Cambio de aceite', 'horometro', 250],
                ['Cambio de filtros', 'horometro', 250],
                ['Revisión de sistema hidráulico', 'horometro', 250],
                ['Cambio de aceite hidráulico', 'horometro', 1000],
                ['Revisión de cables de acero', 'horometro', 250],
                ['Revisión de cilindros hidráulicos', 'horometro', 500],
                ['Certificación de seguridad y operatividad', 'horometro', 1000],
                ['Revisión de frenos', 'horometro', 500],
                ['Revisión de estructura y chasis', 'horometro', 1000],
                ['Cambio de neumáticos', 'horometro', 2000],
                ['Revisión de sistema de dirección', 'horometro', 500],
            ],

            'Grupo Electrógeno / Planta de Luz Estacionaria o Móvil' => [
                ['Cambio de aceite', 'horometro', 250],
                ['Cambio de filtros', 'horometro', 250],
                ['Revisión de sistema de refrigeración', 'horometro', 500],
                ['Revisión de batería', 'horometro', 250],
                ['Revisión de sistema eléctrico', 'horometro', 250],
                ['Calibración de instrumentos y sensores', 'horometro', 500],
                ['Revisión de sistema de escape / emisiones', 'horometro', 500],
                ['Mantenimiento predictivo (análisis de aceite)', 'horometro', 500],
            ],

            'Torre de Iluminación Móvil (Operación nocturna en canchas)' => [
                ['Cambio de aceite', 'horometro', 250],
                ['Cambio de filtros', 'horometro', 250],
                ['Revisión de batería', 'horometro', 250],
                ['Revisión de sistema eléctrico', 'horometro', 250],
                ['Revisión de luces y señalización', 'horometro', 250],
                ['Engrase general de puntos de lubricación', 'horometro', 250],
            ],

            // ============================================================
            // GRUPO 4 · VEHÍCULOS LIVIANOS Y TRANSPORTE DE PERSONAL (KM)
            // ============================================================
            'Camioneta 4x4 (Supervisión y operaciones mineras)' => [
                ['Cambio de aceite', 'kilometraje', 10000],
                ['Cambio de filtros', 'kilometraje', 10000],
                ['Revisión de frenos', 'kilometraje', 10000],
                ['Cambio de neumáticos', 'kilometraje', 40000],
                ['Alineación y balanceo', 'kilometraje', 10000],
                ['Revisión de batería', 'kilometraje', 10000],
                ['Revisión de luces y señalización', 'kilometraje', 10000],
                ['Inspección técnico-mecánica (revisión técnica)', 'kilometraje', 12000],
                ['Revisión de sistema de dirección', 'kilometraje', 20000],
                ['Cambio de correas', 'kilometraje', 40000],
                ['Revisión de sistema de refrigeración', 'kilometraje', 20000],
            ],

            'Minibús (Transporte de personal / cuadrillas)' => [
                ['Cambio de aceite', 'kilometraje', 10000],
                ['Cambio de filtros', 'kilometraje', 10000],
                ['Revisión de frenos', 'kilometraje', 10000],
                ['Cambio de neumáticos', 'kilometraje', 40000],
                ['Alineación y balanceo', 'kilometraje', 10000],
                ['Revisión de batería', 'kilometraje', 10000],
                ['Revisión de luces y señalización', 'kilometraje', 10000],
                ['Inspección técnico-mecánica (revisión técnica)', 'kilometraje', 12000],
                ['Revisión de sistema de refrigeración', 'kilometraje', 20000],
                ['Servicio general', 'kilometraje', 10000],
                ['Revisión de sistema de dirección', 'kilometraje', 20000],
                ['Cambio de correas', 'kilometraje', 40000],
            ],

            'Automóvil (Uso administrativo / Gerencia)' => [
                ['Cambio de aceite', 'kilometraje', 10000],
                ['Cambio de filtros', 'kilometraje', 10000],
                ['Revisión de frenos', 'kilometraje', 10000],
                ['Cambio de neumáticos', 'kilometraje', 40000],
                ['Alineación y balanceo', 'kilometraje', 10000],
                ['Revisión de batería', 'kilometraje', 10000],
                ['Inspección técnico-mecánica (revisión técnica)', 'kilometraje', 12000],
                ['Cambio de correas', 'kilometraje', 40000],
                ['Servicio general', 'kilometraje', 10000],
                ['Revisión de sistema de dirección', 'kilometraje', 20000],
            ],

            'Motocicleta (Mensajería y logística rápida local)' => [
                ['Cambio de aceite', 'kilometraje', 3000],
                ['Cambio de filtros', 'kilometraje', 6000],
                ['Revisión de frenos', 'kilometraje', 5000],
                ['Cambio de neumáticos', 'kilometraje', 15000],
                ['Revisión de batería', 'kilometraje', 6000],
                ['Revisión de luces y señalización', 'kilometraje', 6000],
                ['Revisión de sistema de dirección', 'kilometraje', 6000],
            ],
        ];

        // ── 3. Inserción validando existencia de ambos catálogos ──
        $registros = [];
        $omitidos = [];
        $now = now();

        foreach ($data as $nombreVehiculo => $items) {
            if (!isset($vehiculos[$nombreVehiculo])) {
                $omitidos[] = "Tipo de vehículo no encontrado: {$nombreVehiculo}";
                continue;
            }

            foreach ($items as [$nombreMantenimiento, $tipoMedicion, $frecuencia]) {
                if (!isset($mantenimientos[$nombreMantenimiento])) {
                    $omitidos[] = "Tipo de mantenimiento no encontrado: {$nombreMantenimiento} (vehículo: {$nombreVehiculo})";
                    continue;
                }

                $registros[] = [
                    'id_tipo_vehiculo' => $vehiculos[$nombreVehiculo],
                    'id_tipo_mantenimiento' => $mantenimientos[$nombreMantenimiento],
                    'tipo_medicion' => $tipoMedicion,
                    'frecuencia' => $frecuencia,
                    'estado' => 'ACTIVO',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Inserta en bloques para evitar problemas con placeholders en flotas grandes
        foreach (array_chunk($registros, 200) as $chunk) {
            IntervaloMantenimientoTipo::insert($chunk);
        }

        $this->command->info('✓ Intervalos de mantenimiento por tipo creados: ' . count($registros));

        if (!empty($omitidos)) {
            $this->command->warn('⚠ Elementos omitidos por no encontrar coincidencia exacta de nombre:');
            foreach ($omitidos as $msg) {
                $this->command->warn('  - ' . $msg);
            }
        }
    }
}
