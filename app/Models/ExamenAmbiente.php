<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamenAmbiente extends Model
{
    use HasFactory;

    protected $table = 'examen_ambiente';
    protected $primaryKey = 'id_examen_ambiente';
    public $timestamps = false;
    protected $fillable = ['id_examen', 'id_ambiente'];

    // Relación de muchos a uno (examen_ambiente-examen)
    public function examen()
    {
        return $this->belongsTo(Examen::class, 'id_examen', 'id_examen');
    }

    // Relación de muchos a uno (examen_ambiente-ambiente)
    public function ambiente()
    {
        return $this->belongsTo(Ambiente::class, 'id_ambiente', 'id_ambiente');
    }

    // Relación de uno a muchos (examen_ambiente-registro_ingreso)
    public function registrosIngreso()
    {
        return $this->hasMany(RegistroIngreso::class, 'id_examen_ambiente', 'id_examen_ambiente');
    }
}
