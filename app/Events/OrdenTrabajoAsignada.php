<?php

namespace App\Events;

use App\Models\OrdenTrabajo;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Se emite cuando una orden de trabajo queda asignada a un técnico de
 * mantenimiento (al emitirla o al reasignarla desde la edición).
 */
class OrdenTrabajoAsignada
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public OrdenTrabajo $orden,
    ) {
        //
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
