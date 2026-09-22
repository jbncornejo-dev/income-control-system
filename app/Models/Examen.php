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

    protected $fillable = ['id_asignatura', 'id_periodo', 'id_tipo_examen', 'fecha', 'hora_inicio', 'duracion_minutos', 'normas_generales', 'estado'];

    protected $appends = ['hora_fin', 'estado_actual', 'estado_horario', 'periodo_codigo'];

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
     * Estado de gestión del examen (lo que ve el usuario).
     *
     * Es el que se muestra en el listado y se usa para los chips de filtro:
     *
     * - Las decisiones manuales tienen prioridad:
     *   - 'cancelado': examen programado que se llama off (nunca ocurrió). Definitivo.
     *   - 'anulado': examen en curso que se invalida (lo ocurrido no vale). Definitivo.
     *   - 'suspendido': pausa del registro de ingresos (sigue su ciclo por horario).
     *   Son estados propios de gestión que NO se mezclan con el ciclo.
     * - Sin decisión manual, se deriva del horario (ver `estado_horario`):
     *   programado -> en_curso -> finalizado.
     *
     * Nota: 'suspendido' no altera la duración ni el ciclo; para saber en qué
     * punto del ciclo está un examen suspendido se usa `estado_horario`.
     */
    protected function estadoActual(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->estado === 'cancelado' || $this->estado === 'anulado') {
                return $this->estado;
            }

            if ($this->estado === 'suspendido') {
                return 'suspendido';
            }

            return $this->estado_horario;
        });
    }

    /**
     * Estado derivado SOLO del horario (ciclo de vida), sin considerar la
     * decisión manual: 'programado', 'en_curso' o 'finalizado'. Un examen
     * suspendido sigue su ciclo por aquí mientras su estado de gestión es
     * 'suspendido'.
     */
    protected function estadoHorario(): Attribute
    {
        return Attribute::get(function (): string {
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

    // Relación de muchos a uno (examen-tipo_examen). El nombre visible del
    // examen se deriva del tipo elegido del catálogo (campo "nombre").
    public function tipo()
    {
        return $this->belongsTo(TipoExamen::class, 'id_tipo_examen', 'id_tipo_examen');
    }

    // Relación de muchos a uno (examen-periodo)
    public function periodo()
    {
        return $this->belongsTo(Periodo::class, 'id_periodo', 'id_periodo');
    }

    /**
     * Código compacto del periodo al que pertenece el examen ("I-2026").
     * Requiere la relación "periodo" cargada (o la carga en caliente al acceder).
     */
    protected function periodoCodigo(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->periodo?->codigo);
    }

    // Relación de uno a muchos (examen-examen_ambiente)
    public function examenesAmbientes()
    {
        return $this->hasMany(ExamenAmbiente::class, 'id_examen', 'id_examen');
    }

    // Relación de muchos a muchos (examen-grupo) a través de examen_grupo.
    // Un examen puede cubrir uno o varios grupos; un grupo puede participar en
    // varios exámenes del periodo (p. ej. parciales con fechas distintas).
    public function grupos()
    {
        return $this->belongsToMany(
            Grupo::class,
            'examen_grupo',
            'id_examen',
            'id_grupo',
            'id_examen',
            'id_grupo'
        );
    }

    /**
     * ¿El examen cubre exclusivamente grupos del usuario indicado?
     *
     * El responsable del examen son los dueños de los grupos seleccionados:
     * un examen con grupos de varios docentes lo gestiona el administrador,
     * por lo que ningún docente tiene acceso total. Los exámenes sin grupos
     * no pertenecen a ningún docente.
     */
    public function perteneceIntegramenteA(int $idUsuario): bool
    {
        $total = $this->grupos()->count();

        return $total > 0
            && $this->grupos()->where('grupo.id_usuario', $idUsuario)->count() === $total;
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
