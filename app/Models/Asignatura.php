<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asignatura extends Model
{
    use HasFactory;

    protected $table = 'asignatura';
    protected $primaryKey = 'id_asignatura';
    public $timestamps = false;
    protected $fillable = ['nombre_asignatura'];

    // Relación de uno a muchos (asignatura-examen)
    public function examenes()
    {
        return $this->hasMany(Examen::class, 'id_asignatura', 'id_asignatura');
    }
}
