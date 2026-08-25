<?php

namespace Database\Seeders;

use App\Models\Material;
use Illuminate\Database\Seeder;

class MaterialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Catálogo de materiales transportados en el módulo de Control de
     * Cargas (ver Viaje). Los nombres corresponden a la terminología usada
     * en la minería boliviana para describir el mineral según su etapa de
     * procesamiento.
     */
    public function run(): void
    {
        $materiales = [
            'Broza',
            'Concentrado',
            'Cola',
            'Relave',
            'Desmonte',
            'Mineral en Bruto (Todo Uno)',
            'Complejos',
            'Estaño',
            'Zinc',
            'Plomo',
            'Plata',
            'Sobrantes',
        ];

        foreach ($materiales as $material) {
            Material::firstOrCreate(['material' => $material]);
        }

        $this->command->info('✓ Materiales creados exitosamente');
    }
}
