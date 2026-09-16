<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Token FCM de un dispositivo (app Flutter) registrado por un usuario para
 * recibir notificaciones push. Ver App\Channels\FcmChannel.
 */
#[Fillable([
    'id_usuario',
    'token',
    'plataforma',
    'ultima_actividad',
])]
class Dispositivo extends Model
{
    protected $table = 'dispositivos';

    protected function casts(): array
    {
        return [
            'ultima_actividad' => 'datetime',
        ];
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }
}
