<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PeriodoTipoExamen extends Model
{
    use HasFactory;

    protected $table = 'periodo_tipo_examen';

    protected $primaryKey = 'id_periodo_tipo_examen';

    public $timestamps = false;

    protected $fillable = ['id_periodo', 'id_tipo_examen', 'orden'];

    // Relación de muchos a uno (periodo_tipo_examen-periodo)
    public function periodo()
    {
        return $this->belongsTo(Periodo::class, 'id_periodo', 'id_periodo');
    }

    // Relación de muchos a uno (periodo_tipo_examen-tipo_examen)
    public function tipoExamen()
    {
        return $this->belongsTo(TipoExamen::class, 'id_tipo_examen', 'id_tipo_examen');
    }
}
