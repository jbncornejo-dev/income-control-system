<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Examen extends Model
{
    use HasFactory;

    protected $table = 'examen';

    protected $primaryKey = 'id_examen';

    public $timestamps = false;

    protected $fillable = ['id_asignatura', 'fecha', 'hora_inicio', 'duracion_minutos', 'normas_generales'];

    protected $appends = ['hora_fin'];

    /**
     * Hora de finalización calculada a partir de la hora de inicio y la duración.
     */
    protected function horaFin(): Attribute
    {
        return Attribute::get(function (): ?string {
            if ($this->hora_inicio === null || $this->duracion_minutos === null) {
                return null;
            }

            return Carbon::parse($this->hora_inicio)
                ->addMinutes((int) $this->duracion_minutos)
                ->format('H:i');
        });
    }

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

    // Relación de uno a muchos a través de examen_ambiente (examen-registro_ingreso)
    public function registrosIngreso()
    {
        return $this->hasManyThrough(
            RegistroIngreso::class,
            ExamenAmbiente::class,
            'id_examen',
            'id_examen_ambiente',
            'id_examen',
            'id_examen_ambiente'
        );
    }
}
