<?php

namespace Database\Seeders;

use App\Models\Repuesto;
use Illuminate\Database\Seeder;

class RepuestoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Catálogo de ejemplo de repuestos/insumos usados al registrar el
     * detalle de una orden de trabajo (ver DetalleMantenimiento).
     */
    public function run(): void
    {
        $repuestos = [
            [
                'nombre_repuesto' => 'Filtro de aceite',
                'codigo_repuesto' => 'REP-0001',
                'descripcion_repuesto' => 'Filtro de aceite de motor, uso general',
                'unidad_medida' => 'UNIDAD',
                'stock_actual' => 25,
                'estado_repuesto' => 'ACTIVO',
            ],
            [
                'nombre_repuesto' => 'Filtro de aire',
                'codigo_repuesto' => 'REP-0002',
                'descripcion_repuesto' => 'Filtro de aire de motor',
                'unidad_medida' => 'UNIDAD',
                'stock_actual' => 20,
                'estado_repuesto' => 'ACTIVO',
            ],
            [
                'nombre_repuesto' => 'Filtro de combustible',
                'codigo_repuesto' => 'REP-0003',
                'descripcion_repuesto' => 'Filtro de combustible diésel',
                'unidad_medida' => 'UNIDAD',
                'stock_actual' => 18,
                'estado_repuesto' => 'ACTIVO',
            ],
            [
                'nombre_repuesto' => 'Aceite de motor 15W-40',
                'codigo_repuesto' => 'REP-0004',
                'descripcion_repuesto' => 'Aceite mineral para motor diésel',
                'unidad_medida' => 'LITRO',
                'stock_actual' => 200,
                'estado_repuesto' => 'ACTIVO',
            ],
            [
                'nombre_repuesto' => 'Aceite hidráulico',
                'codigo_repuesto' => 'REP-0005',
                'descripcion_repuesto' => 'Aceite hidráulico para maquinaria pesada',
                'unidad_medida' => 'LITRO',
                'stock_actual' => 150,
                'estado_repuesto' => 'ACTIVO',
            ],
            [
                'nombre_repuesto' => 'Pastillas de freno',
                'codigo_repuesto' => 'REP-0006',
                'descripcion_repuesto' => 'Juego de pastillas de freno delanteras',
                'unidad_medida' => 'JUEGO',
                'stock_actual' => 12,
                'estado_repuesto' => 'ACTIVO',
            ],
            [
                'nombre_repuesto' => 'Batería 12V',
                'codigo_repuesto' => 'REP-0007',
                'descripcion_repuesto' => 'Batería de arranque 12V 150Ah',
                'unidad_medida' => 'UNIDAD',
                'stock_actual' => 8,
                'estado_repuesto' => 'ACTIVO',
            ],
            [
                'nombre_repuesto' => 'Neumático 295/80 R22.5',
                'codigo_repuesto' => 'REP-0008',
                'descripcion_repuesto' => 'Neumático radial para camión',
                'unidad_medida' => 'UNIDAD',
                'stock_actual' => 10,
                'estado_repuesto' => 'ACTIVO',
            ],
            [
                'nombre_repuesto' => 'Correa de distribución',
                'codigo_repuesto' => 'REP-0009',
                'descripcion_repuesto' => 'Correa dentada de distribución',
                'unidad_medida' => 'UNIDAD',
                'stock_actual' => 6,
                'estado_repuesto' => 'ACTIVO',
            ],
            [
                'nombre_repuesto' => 'Grasa multipropósito',
                'codigo_repuesto' => 'REP-0010',
                'descripcion_repuesto' => 'Grasa para engrase general de puntos de lubricación',
                'unidad_medida' => 'KILOGRAMO',
                'stock_actual' => 40,
                'estado_repuesto' => 'ACTIVO',
            ],
            [
                'nombre_repuesto' => 'Manguera hidráulica',
                'codigo_repuesto' => 'REP-0011',
                'descripcion_repuesto' => 'Manguera hidráulica de alta presión, por metro',
                'unidad_medida' => 'METRO',
                'stock_actual' => 30,
                'estado_repuesto' => 'ACTIVO',
            ],
            [
                'nombre_repuesto' => 'Bujías',
                'codigo_repuesto' => 'REP-0012',
                'descripcion_repuesto' => 'Bujía de encendido estándar',
                'unidad_medida' => 'UNIDAD',
                'stock_actual' => 0,
                'estado_repuesto' => 'AGOTADO',
            ],
        ];

        foreach ($repuestos as $repuesto) {
            Repuesto::updateOrCreate(
                ['codigo_repuesto' => $repuesto['codigo_repuesto']],
                $repuesto
            );
        }

        $this->command->info('✓ Repuestos creados exitosamente');
    }
}
