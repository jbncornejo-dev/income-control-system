<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoExamen extends Model
{
    use HasFactory;

    protected $table = 'tipo_examen';

    protected $primaryKey = 'id_tipo_examen';

    public $timestamps = false;

    protected $fillable = ['nombre', 'codigo', 'activo'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    // Relación de uno a muchos (tipo_examen-examen)
    public function examenes()
    {
        return $this->hasMany(Examen::class, 'id_tipo_examen', 'id_tipo_examen');
    }

    // Relación de muchos a muchos (tipo_examen-periodo) a través de
    // periodo_tipo_examen: el tipo puede integrar el plan de varios periodos.
    public function periodos()
    {
        return $this->belongsToMany(
            Periodo::class,
            'periodo_tipo_examen',
            'id_tipo_examen',
            'id_periodo',
            'id_tipo_examen',
            'id_periodo'
        )->withPivot('orden');
    }
}
