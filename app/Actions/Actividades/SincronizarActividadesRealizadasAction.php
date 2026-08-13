<?php

namespace App\Actions\Actividades;
use App\Models\OperacionDiaria;
use Illuminate\Support\Str;
use App\Models\Actividad;

class SincronizarActividadesRealizadasAction
{
    public function execute(OperacionDiaria $operacionDiaria, array $actividades): void
    {
       //se busca primero ver si la actividad ya existe en la base de datos verificando el nombre_normalizado, sino existe se crea una nueva actividad
        // se guard en la tabla actividad_realizada, la relacion y los detalles, verificar que no re registre 2 veces la misma actividad contodos los campos iguales

        $operacionDiaria->actividadesRealizadas()->detach();

        foreach ($actividades as $actividadData) {
            $nombreNormalizado = Str::of($actividadData['actividad'])
                ->lower()->ascii()->trim();

            $actividad = Actividad::firstOrCreate(
                ['nombre_normalizado' => $nombreNormalizado],
                [
                    'nombre_actividad' => $actividadData['actividad'],
                    'unidad_medida' => $actividadData['unidad_medida'],
                    'id_area' => $operacionDiaria->id_area,
                    'estado_actividad' => 'ACTIVO',
                    'ultimo_uso' => now(),

                ]
            );

            // eliminar la relación si ya existe para evitar duplicados


            $operacionDiaria->actividadesRealizadas()->attach($actividad->id, [
                'origen' => $actividadData['origen'],
                'destino' => $actividadData['destino'],
                'lugar' => $actividadData['lugar'],
                'cantidad' => $actividadData['cantidad'],
                'unidad_medida' => $actividadData['unidad_medida'],
                'hora_inicio' => $actividadData['hora_inicio'],
                'hora_fin' => $actividadData['hora_fin'],
            ]);
        }


    }
}
