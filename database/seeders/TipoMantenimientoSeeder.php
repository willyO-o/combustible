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
        ];

        foreach ($tiposMantenimiento as $tipo) {
            TipoMantenimiento::updateOrCreate(
                ['tipo_mantenimiento' => $tipo['tipo_mantenimiento']],
                ['estado_tipo_mantenimiento' => $tipo['estado_tipo_mantenimiento']]
            );
        }

        $this->command->info('✓ Tipos de mantenimiento creados exitosamente');
    }
}
