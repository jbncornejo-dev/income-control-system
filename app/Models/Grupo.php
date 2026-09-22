<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    use HasFactory;

    protected $table = 'grupo';

    protected $primaryKey = 'id_grupo';

    public $timestamps = false;

    protected $fillable = ['id_asignatura', 'id_usuario', 'gestion', 'nombre_grupo'];

    // Relación de muchos a uno (grupo - asignatura)
    public function asignatura()
    {
        return $this->belongsTo(Asignatura::class, 'id_asignatura', 'id_asignatura');
    }

    // Relación de muchos a uno (grupo - usuario/docente)
    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id');
    }

    // Relación de uno a muchos (grupo - inscripcion)
    public function inscripciones()
    {
        return $this->hasMany(Inscripcion::class, 'id_grupo', 'id_grupo');
    }

    // Relación de muchos a muchos (grupo-examen) a través de examen_grupo.
    public function examenes()
    {
        return $this->belongsToMany(
            Examen::class,
            'examen_grupo',
            'id_grupo',
            'id_examen',
            'id_grupo',
            'id_examen'
        );
    }
}
