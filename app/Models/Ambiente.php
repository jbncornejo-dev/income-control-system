<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ambiente extends Model
{
    use HasFactory;

    protected $table = 'ambiente';
    protected $primaryKey = 'id_ambiente';
    public $timestamps = false;
    protected $fillable = ['nombre_ambiente', 'capacidad'];

    // Relación de uno a muchos (ambiente-examen_ambiente)
    public function examenesAmbientes()
    {
        return $this->hasMany(ExamenAmbiente::class, 'id_ambiente', 'id_ambiente');
    }
}
