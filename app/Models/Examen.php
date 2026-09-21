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

    protected $fillable = ['id_asignatura', 'id_periodo', 'fecha', 'hora_inicio', 'duracion_minutos', 'normas_generales', 'estado'];

    protected $appends = ['hora_fin', 'estado_actual', 'periodo_nombre'];

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

    /**
     * Estado actual del examen (ciclo de vida).
     *
     * - Solo 'cancelado' (anulado) sobreescribe el ciclo: es una decisión
     *   definitiva e independiente del horario.
     * - 'suspendido' NO es parte del ciclo de vida: es una pausa temporal
     *   del registro de ingresos. El examen sigue su curso según el horario
     *   (programado -> en_curso -> finalizado) y su duración no se altera.
     */
    protected function estadoActual(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->estado === 'cancelado') {
                return 'cancelado';
            }

            $inicio = Carbon::parse($this->fecha.' '.$this->hora_inicio);
            $fin = $inicio->copy()->addMinutes((int) $this->duracion_minutos);

            if (now()->lt($inicio)) {
                return 'programado';
            }

            if (now()->lt($fin)) {
                return 'en_curso';
            }

            return 'finalizado';
        });
    }

    // Relación de muchos a uno (examen-asignatura)
    public function asignatura()
    {
        return $this->belongsTo(Asignatura::class, 'id_asignatura', 'id_asignatura');
    }

    // Relación de muchos a uno (examen-periodo/semestre)
    public function periodo()
    {
        return $this->belongsTo(Periodo::class, 'id_periodo', 'id_periodo');
    }

    /**
     * Nombre legible del semestre al que pertenece el examen.
     * Requiere la relación "periodo" cargada (o la carga en caliente al acceder).
     */
    protected function periodoNombre(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->periodo?->nombre);
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
