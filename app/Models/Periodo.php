<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Periodo extends Model
{
    use HasFactory;

    protected $table = 'periodo';

    protected $primaryKey = 'id_periodo';

    public $timestamps = false;

    protected $fillable = ['gestion', 'tipo', 'numero', 'fecha_inicio', 'fecha_fin'];

    protected $appends = ['nombre', 'codigo'];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'fecha_inicio' => 'date:Y-m-d',
            'fecha_fin' => 'date:Y-m-d',
        ];
    }

    /**
     * Nombre legible del periodo, p. ej. "Gestión 2026 · Primer Semestre".
     * Pensado para contextos descriptivos (tooltips, módulo de administración).
     */
    protected function nombre(): Attribute
    {
        return Attribute::get(function (): string {
            $nombres = [
                'semestre' => [1 => 'Primer Semestre', 2 => 'Segundo Semestre', 3 => 'Tercer Semestre', 4 => 'Cuarto Semestre'],
                'trimestre' => [1 => 'Primer Trimestre', 2 => 'Segundo Trimestre', 3 => 'Tercer Trimestre'],
                'cuatrimestre' => [1 => 'Primer Cuatrimestre', 2 => 'Segundo Cuatrimestre', 3 => 'Tercer Cuatrimestre'],
                'bimestre' => [1 => 'Primer Bimestre', 2 => 'Segundo Bimestre', 3 => 'Tercer Bimestre', 4 => 'Cuarto Bimestre', 5 => 'Quinto Bimestre', 6 => 'Sexto Bimestre'],
            ];
            $etiqueta = $nombres[$this->tipo][$this->numero] ?? ucfirst((string) $this->tipo).' '.$this->numero;

            return "Gestión {$this->gestion} · {$etiqueta}";
        });
    }

    /**
     * Código compacto del periodo, p. ej. "I-2026" para el primer semestre.
     * Formato: {periodo}-{gestion}. El semestre usa numeración romana; el resto
     * de tipos usan el número seguido de la inicial del tipo ("1T-2026").
     */
    protected function codigo(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->tipo === 'semestre') {
                $romanos = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X'];

                return ($romanos[$this->numero] ?? (string) $this->numero).'-'.$this->gestion;
            }

            $inicial = strtoupper(substr((string) $this->tipo, 0, 1)); // T, C o B

            return $this->numero.$inicial.'-'.$this->gestion;
        });
    }

    // Relación de uno a muchos (periodo-examen)
    public function examenes()
    {
        return $this->hasMany(Examen::class, 'id_periodo', 'id_periodo');
    }

    // Relación de muchos a muchos (periodo-tipo_examen) a través de
    // periodo_tipo_examen. Es el "plan de evaluación" del periodo: qué tipos de
    // examen rigen en esa gestión y en qué orden (pivot.orden).
    public function tiposExamen()
    {
        return $this->belongsToMany(
            TipoExamen::class,
            'periodo_tipo_examen',
            'id_periodo',
            'id_tipo_examen',
            'id_periodo',
            'id_tipo_examen'
        )->withPivot('orden');
    }
}
