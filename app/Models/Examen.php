<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Examen extends Model
{
    use HasFactory;

    protected $table = 'examen';
    protected $primaryKey = 'id_examen';
    public $timestamps = false;
    protected $fillable = ['id_asignatura', 'fecha', 'hora_inicio', 'duracion_minutos', 'normas_generales'];

    // Relación de muchos a uno (examen-asignatura)
    public function asignatura()
    {
        return $this->belongsTo(Asignatura::class, 'id_asignatura', 'id_asignatura');
    }

    // Relación de uno a muchos (examen-examen_ambiente)
    public function examenesAmbientes()
    {
        return $this->hasMany(ExamenAmbiente::class, 'id_examen', 'id_examen');
    }

    // Relación de uno a muchos (examen-habilitacion)
    public function habilitaciones()
    {
        return $this->hasMany(Habilitacion::class, 'id_examen', 'id_examen');
    }

    // Relación de uno a muchos (examen-incidencia)
    public function incidencias()
    {
        return $this->hasMany(Incidencia::class, 'id_examen', 'id_examen');
    }
}
