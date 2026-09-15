<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistroIngreso extends Model
{
    use HasFactory;

    protected $table = 'registro_ingreso';
    protected $primaryKey = 'id_registro';
    public $timestamps = false;
    protected $fillable = ['id_estudiante', 'id_examen_ambiente', 'id_usuario', 'fecha_hora_ingreso'];

    // Relación de muchos a uno (registro_ingreso-estudiante)
    public function estudiante()
    {
        return $this->belongsTo(Estudiante::class, 'id_estudiante', 'id_estudiante');
    }

    // Relación de muchos a uno (registro_ingreso-examen_ambiente)
    public function examenAmbiente()
    {
        return $this->belongsTo(ExamenAmbiente::class, 'id_examen_ambiente', 'id_examen_ambiente');
    }

    // Relación de muchos a uno (registro_ingreso-user)
    public function user()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id');
    }
}
