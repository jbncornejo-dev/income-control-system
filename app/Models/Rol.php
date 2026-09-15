<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    use HasFactory;

    protected $table = 'rol';
    protected $primaryKey = 'id_rol';
    public $timestamps = false;
    protected $fillable = ['nombre_rol'];

    // Relación de uno a muchos (rol-users)
    public function users()
    {
        return $this->hasMany(User::class, 'id_rol', 'id_rol');
    }
}
