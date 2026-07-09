<?php

namespace Database\Seeders;

use App\Models\TipoCombustible;
use Illuminate\Database\Seeder;

class TipoCombustibleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tiposCombustibles = [
            [
                'tipo_combustible' => 'Gasolina 95',
                'estado_tipo_combustible' => 'ACTIVO',
            ],
            [
                'tipo_combustible' => 'Gasolina 97',
                'estado_tipo_combustible' => 'ACTIVO',
            ],
            [
                'tipo_combustible' => 'Diésel',
                'estado_tipo_combustible' => 'ACTIVO',
            ],
            [
                'tipo_combustible' => 'Gas',
                'estado_tipo_combustible' => 'ACTIVO',
            ],
            [
                'tipo_combustible' => 'Gasolina 91',
                'estado_tipo_combustible' => 'ACTIVO',
            ],
        ];

        foreach ($tiposCombustibles as $tipo) {
            TipoCombustible::updateOrCreate(
                ['tipo_combustible' => $tipo['tipo_combustible']],
                ['estado_tipo_combustible' => $tipo['estado_tipo_combustible']]
            );
        }

        $this->command->info('✓ Tipos de combustibles creados exitosamente');
    }
}
