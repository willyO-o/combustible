<?php

namespace Database\Seeders;

use App\Models\Grifo;
use Illuminate\Database\Seeder;

class GrifoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $grifos = [
            [
                'razon_social' => 'Grifo Local PlusMetals',
                'nit' => '000000',
                'direccion' => 'Av. Principal 100',
                'ciudad' => 'Oruro',
                'telefono' => '2-2123456',
                'estado_grifo' => 'ACTIVO',
                'es_principal' => true,
            ],
            [
                'razon_social' => 'Gasolinera Central S.A.',
                'nit' => '123456789-0',
                'direccion' => 'Av. Principal 100',
                'ciudad' => 'La Paz',
                'telefono' => '2-2123456',
                'estado_grifo' => 'ACTIVO',
            ],
            [
                'razon_social' => 'Combustibles Oruro S.R.L.',
                'nit' => '123456790-0',
                'direccion' => 'Calle 6ta 250',
                'ciudad' => 'Oruro',
                'telefono' => '2-2654321',
                'estado_grifo' => 'ACTIVO',
            ],
            [
                'razon_social' => 'Distribuidora de Combustibles Santa Cruz',
                'nit' => '123456791-0',
                'direccion' => 'Av. Brasil 500',
                'ciudad' => 'Santa Cruz',
                'telefono' => '3-3987654',
                'estado_grifo' => 'ACTIVO',
            ],
            [
                'razon_social' => 'Grifo Cochabamba Express',
                'nit' => '123456792-0',
                'direccion' => 'Pasaje Sur 180',
                'ciudad' => 'Cochabamba',
                'telefono' => '4-4456789',
                'estado_grifo' => 'ACTIVO',
            ],
            [
                'razon_social' => 'Combustibles Potosí Ltda.',
                'nit' => '123456793-0',
                'direccion' => 'Boulevard Norte 350',
                'ciudad' => 'Potosí',
                'telefono' => '2-2345678',
                'estado_grifo' => 'ACTIVO',
            ],
        ];

        foreach ($grifos as $grifo) {
            Grifo::updateOrCreate(
                ['nit' => $grifo['nit']],
                [
                    'razon_social' => $grifo['razon_social'],
                    'direccion' => $grifo['direccion'],
                    'ciudad' => $grifo['ciudad'],
                    'telefono' => $grifo['telefono'],
                    'estado_grifo' => $grifo['estado_grifo'],
                ]
            );
        }

        $this->command->info('✓ Grifos creados exitosamente');
    }
}
