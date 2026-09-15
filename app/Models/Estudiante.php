<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Estudiante extends Model
{
    use HasFactory;

    protected $table = 'estudiante';
    protected $primaryKey = 'id_estudiante';
    public $timestamps = false;
    protected $fillable = ['codigo_universitario', 'documento_identidad', 'nombres', 'apellidos', 'codigo_qr'];

    // Relación de uno a muchos (estudiante-habilitacion)
    public function habilitaciones()
    {
        return $this->hasMany(Habilitacion::class, 'id_estudiante', 'id_estudiante');
    }

    // Relación de uno a muchos (estudiante-registro_ingreso)
    public function registrosIngreso()
    {
        return $this->hasMany(RegistroIngreso::class, 'id_estudiante', 'id_estudiante');
    }

    // Relación de uno a muchos (estudiante-incidencia)
    public function incidencias()
    {
        return $this->hasMany(Incidencia::class, 'id_estudiante', 'id_estudiante');
    }
}
