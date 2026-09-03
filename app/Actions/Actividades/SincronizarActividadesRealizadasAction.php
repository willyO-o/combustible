<?php

namespace App\Actions\Actividades;

use App\Models\Actividad;
use App\Models\OperacionDiaria;
use Illuminate\Support\Str;

class SincronizarActividadesRealizadasAction
{
    public function execute(OperacionDiaria $operacionDiaria, array $actividades): void
    {
        // se busca primero ver si la actividad ya existe en la base de datos verificando el nombre_normalizado, sino existe se crea una nueva actividad
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
                'id_material' => $actividadData['id_material'] ?? null,
                // origen/destino (medición por kilometraje) y lugar (por
                // horómetro) son mutuamente excluyentes: el cliente sólo envía
                // el par que aplica a su vehículo, así que el otro puede faltar.
                'origen' => $actividadData['origen'] ?? null,
                'destino' => $actividadData['destino'] ?? null,
                'lugar' => $actividadData['lugar'] ?? null,
                'cantidad' => $actividadData['cantidad'],
                'unidad_medida' => $actividadData['unidad_medida'],
                'hora_inicio' => $actividadData['hora_inicio'],
                'hora_fin' => $actividadData['hora_fin'],
            ]);
        }

    }
}
