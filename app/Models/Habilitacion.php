<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Habilitacion extends Model
{
    use HasFactory;

    protected $table = 'habilitacion';
    protected $primaryKey = 'id_habilitacion';
    public $timestamps = false;
    protected $fillable = ['id_estudiante', 'id_examen', 'estado_habilitado', 'motivo_inhabilitacion', 'normas_particulares'];

    protected function casts(): array
    {
        return [
            'estado_habilitado' => 'boolean',
        ];
    }

    // Relación de muchos a uno (habilitacion-estudiante)
    public function estudiante()
    {
        return $this->belongsTo(Estudiante::class, 'id_estudiante', 'id_estudiante');
    }

    // Relación de muchos a uno (habilitacion-examen)
    public function examen()
    {
        return $this->belongsTo(Examen::class, 'id_examen', 'id_examen');
    }
}
