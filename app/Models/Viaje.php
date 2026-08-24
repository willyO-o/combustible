<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'id_carga_material',
    'id_material',
    'id_usuario_registro',
    'foto',
    'origen',
    'destino',
    'detalle',
])]
class Viaje extends Model
{
    use HasFactory;

    protected $table = 'viaje';

    protected $appends = [
        'foto_url',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $viaje) {
            $viaje->id_usuario_registro = Auth::id();
        });
    }

    public function getFotoUrlAttribute()
    {
        return $this->foto ? Storage::disk('public')->url($this->foto) : null;
    }

    // Relaciones
    public function cargaMaterial()
    {
        return $this->belongsTo(CargaMaterial::class, 'id_carga_material');
    }

    public function material()
    {
        return $this->belongsTo(Material::class, 'id_material');
    }

    public function usuarioRegistro()
    {
        return $this->belongsTo(User::class, 'id_usuario_registro');
    }
}
