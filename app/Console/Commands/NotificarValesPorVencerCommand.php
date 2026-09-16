<?php

namespace App\Console\Commands;

use App\Models\Vale;
use App\Notifications\ValePorVencerNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Avisa al conductor cuando su vale PENDIENTE está por vencer, para que
 * alcance a usarlo. Corre una vez al día (ver Schedule::command() en
 * routes/console.php); notificado_vencimiento_at evita volver a avisar el
 * mismo vale en corridas siguientes.
 */
class NotificarValesPorVencerCommand extends Command
{
    /**
     * Días de anticipación con los que se avisa antes del vencimiento.
     */
    private const DIAS_ANTICIPACION = 2;

    protected $signature = 'vales:notificar-vencimiento';

    protected $description = 'Notifica a los conductores cuyo vale de combustible está por vencer';

    public function handle(): int
    {
        $vales = Vale::whereNull('notificado_vencimiento_at')
            ->where('estado_vale', 'PENDIENTE')
            ->whereBetween('fecha_vencimiento', [now(), now()->addDays(self::DIAS_ANTICIPACION)])
            ->with('conductor.user')
            ->get();

        foreach ($vales as $vale) {
            $usuario = $vale->conductor?->user;

            if ($usuario) {
                Notification::send($usuario, new ValePorVencerNotification($vale));
            }

            $vale->update(['notificado_vencimiento_at' => now()]);
        }

        $this->info("Vales revisados: {$vales->count()}.");

        return self::SUCCESS;
    }
}
