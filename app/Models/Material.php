<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'material',
])]
class Material extends Model
{
    use HasFactory;

    protected $table = 'material';

    // La tabla `material` no tiene columnas created_at/updated_at.
    public $timestamps = false;
}
