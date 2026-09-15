<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Incidencia extends Model
{
    use HasFactory;

    protected $table = 'incidencia';
    protected $primaryKey = 'id_incidencia';
    public $timestamps = false;
    protected $fillable = ['id_estudiante', 'id_examen', 'id_usuario', 'tipo_incidencia', 'descripcion_motivo', 'fecha_hora'];

    // Relación de muchos a uno (incidencia-estudiante)
    public function estudiante()
    {
        return $this->belongsTo(Estudiante::class, 'id_estudiante', 'id_estudiante');
    }

    // Relación de muchos a uno (incidencia-examen)
    public function examen()
    {
        return $this->belongsTo(Examen::class, 'id_examen', 'id_examen');
    }

    // Relación de muchos a uno (incidencia-user)
    public function user()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id');
    }
}
