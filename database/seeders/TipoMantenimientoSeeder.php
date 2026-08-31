<?php

namespace Database\Seeders;

use App\Models\TipoMantenimiento;
use Illuminate\Database\Seeder;

class TipoMantenimientoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tiposMantenimiento = [
            // ===== Mantenimiento general (aplica a todo tipo de vehículo/equipo) =====
            [
                'tipo_mantenimiento' => 'Cambio de aceite',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Revisión mecánica',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Cambio de filtros',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Revisión de frenos',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Cambio de neumáticos',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Alineación y balanceo',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Revisión de batería',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Servicio general',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Revisión de sistema de refrigeración',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Cambio de correas',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Revisión de sistema eléctrico',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Revisión de luces y señalización',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Inspección técnico-mecánica (revisión técnica)',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Cambio de líquido de frenos',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Revisión de sistema de dirección',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],

            // ===== Vehículos de carga (camiones cisterna, tráilers, tractocamiones) =====
            [
                'tipo_mantenimiento' => 'Revisión de tanque cisterna',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Calibración de válvulas de descarga',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Revisión de sistema de frenos neumáticos',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Revisión de quinta rueda',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Revisión de suspensión neumática',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Revisión de acoplamiento (king pin / perno rey)',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Revisión de mangueras y conexiones neumáticas',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Prueba de fugas en sistema de gas/combustible',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],

            // ===== Maquinaria pesada (tractores, palas, retroexcavadoras, motoniveladoras) =====
            [
                'tipo_mantenimiento' => 'Revisión de sistema hidráulico',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Cambio de aceite hidráulico',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Cambio de filtros hidráulicos',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Revisión de mangueras hidráulicas',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Revisión de tren de rodaje / orugas',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Engrase general de puntos de lubricación',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Revisión de cucharón / balde',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Revisión de cilindros hidráulicos',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Revisión de transmisión',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Cambio de aceite de transmisión',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Revisión de sistema de enfriamiento del motor',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Calibración de horómetro',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Revisión de estructura y chasis',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Revisión de rodamientos y bocinas',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],

            // ===== Montacargas / equipos de izaje =====
            [
                'tipo_mantenimiento' => 'Revisión de mástil y cadenas',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Revisión de horquillas',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Revisión de sistema de contrapeso',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Revisión y carga de batería (equipos eléctricos)',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Revisión de cables de acero',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],

            // ===== Perforadoras / taladros =====
            [
                'tipo_mantenimiento' => 'Revisión de sistema de perforación',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Cambio de brocas y accesorios de perforación',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Revisión de compresor de aire',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Revisión de sistema de percusión/rotación',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],

            // ===== Seguridad, certificación y calibración general =====
            [
                'tipo_mantenimiento' => 'Certificación de seguridad y operatividad',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Calibración de instrumentos y sensores',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Revisión de sistema de escape / emisiones',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],
            [
                'tipo_mantenimiento' => 'Mantenimiento predictivo (análisis de aceite)',
                'estado_tipo_mantenimiento' => 'ACTIVO',
            ],

            // ===== Controles de operación diaria (ambito operacion_diaria) =====
            // Los registra el conductor en el parte diario, no el taller.
            [
                'tipo_mantenimiento' => 'Aceite de motor',
                'estado_tipo_mantenimiento' => 'ACTIVO',
                'ambito' => 'operacion_diaria',
                'tipo_valor' => 'cantidad',
                'unidad_medida' => 'Litros',
            ],
            [
                'tipo_mantenimiento' => 'Aceite de transmisión',
                'estado_tipo_mantenimiento' => 'ACTIVO',
                'ambito' => 'operacion_diaria',
                'tipo_valor' => 'cantidad',
                'unidad_medida' => 'Litros',
            ],
            [
                'tipo_mantenimiento' => 'Aceite hidráulico',
                'estado_tipo_mantenimiento' => 'ACTIVO',
                'ambito' => 'operacion_diaria',
                'tipo_valor' => 'cantidad',
                'unidad_medida' => 'Litros',
            ],
            [
                'tipo_mantenimiento' => 'Grasa',
                'estado_tipo_mantenimiento' => 'ACTIVO',
                'ambito' => 'operacion_diaria',
                'tipo_valor' => 'booleano',
            ],
            [
                'tipo_mantenimiento' => 'Soplado de filtro',
                'estado_tipo_mantenimiento' => 'ACTIVO',
                'ambito' => 'operacion_diaria',
                'tipo_valor' => 'booleano',
            ],
        ];

        foreach ($tiposMantenimiento as $tipo) {
            $ambito = $tipo['ambito'] ?? 'taller';

            TipoMantenimiento::updateOrCreate(
                [
                    'tipo_mantenimiento' => $tipo['tipo_mantenimiento'],
                    'ambito' => $ambito,
                ],
                [
                    'estado_tipo_mantenimiento' => $tipo['estado_tipo_mantenimiento'],
                    'tipo_valor' => $tipo['tipo_valor'] ?? null,
                    'unidad_medida' => $tipo['unidad_medida'] ?? null,
                ]
            );
        }

        $this->command->info('✓ Tipos de mantenimiento creados exitosamente');
    }
}
