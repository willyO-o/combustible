<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Area;

class AreaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $areas = [
            [
                'nombre_area' => 'Operaciones Mina / Extracción',
                'descripcion_area' => 'Responsable de la extracción del mineral: mina, geología, planificación minera y topografía. Opera principalmente maquinaria pesada de extracción.',
                'estado_area' => 'ACTIVO',
            ],
            [
                'nombre_area' => 'Planta de Procesamiento y Refinación',
                'descripcion_area' => 'Encargada del procesamiento del mineral: chancado, molienda, concentración, fundición, refinación y control de calidad metalúrgico. Opera equipos de planta y maniobras.',
                'estado_area' => 'ACTIVO',
            ],
            [
                'nombre_area' => 'Mantenimiento y Logística',
                'descripcion_area' => 'Soporte transversal a la operación: mantenimiento mecánico y eléctrico, almacenes, suministros, transporte y logística de carga. Opera transporte pesado.',
                'estado_area' => 'ACTIVO',
            ],
            [
                'nombre_area' => 'Administración y Servicios Generales',
                'descripcion_area' => 'Gestión administrativa, financiera, de recursos humanos, seguridad, medio ambiente y campamento. Opera vehículos livianos.',
                'estado_area' => 'ACTIVO',
            ],
        ];

        foreach ($areas as $area) {
            Area::create($area);
        }
    }
}
