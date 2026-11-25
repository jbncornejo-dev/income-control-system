<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConflictoIngreso extends Model
{
    use HasFactory;

    protected $table = 'conflicto_ingreso';
    protected $primaryKey = 'id_conflicto';
    public $timestamps = false;

    protected $fillable = [
        'uuid_offline',
        'id_estudiante',
        'id_examen_ambiente',
        'id_usuario',
        'fecha_hora_intento',
        'motivo',
    ];

    public function estudiante()
    {
        return $this->belongsTo(Estudiante::class, 'id_estudiante', 'id_estudiante');
    }

    public function examenAmbiente()
    {
        return $this->belongsTo(ExamenAmbiente::class, 'id_examen_ambiente', 'id_examen_ambiente');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id');
    }
}