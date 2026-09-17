<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

#[Fillable([
    'id_conductor',
    'tipo_documento',
    'numero_documento',
    'categoria',
    'fecha_emision',
    'fecha_vencimiento',
    'archivo',
    'estado_documento',
    'observacion',
])]
class DocumentoConductor extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'documento_conductor';

    protected $appends = ['archivo_url'];

    protected function casts()
    {
        return [
            'fecha_emision' => 'date',
            'fecha_vencimiento' => 'date',
        ];
    }

    public function getArchivoUrlAttribute()
    {
        return $this->archivo ? asset('storage/'.$this->archivo) : null;
    }

    // Relaciones
    public function conductor()
    {
        return $this->belongsTo(Conductor::class, 'id_conductor');
    }
}
