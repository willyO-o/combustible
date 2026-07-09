<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
#[Fillable([
    'rol',
    'estado_rol',
])]
class Rol extends Model
{
    //
    protected $table = 'rol';

    public function users()
    {
        return $this->hasMany(User::class, 'id_rol');
    }
}
