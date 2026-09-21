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

    protected $fillable = ['gestion', 'semestre', 'fecha_inicio', 'fecha_fin'];

    protected $appends = ['nombre'];

    protected function casts(): array
    {
        return [
            'semestre' => 'integer',
            'fecha_inicio' => 'date:Y-m-d',
            'fecha_fin' => 'date:Y-m-d',
        ];
    }

    /**
     * Nombre legible del periodo, p. ej. "Gestión 2026 · Segundo Semestre".
     */
    protected function nombre(): Attribute
    {
        return Attribute::get(function (): string {
            $semestres = [1 => 'Primer Semestre', 2 => 'Segundo Semestre'];

            return "Gestión {$this->gestion} · ".($semestres[$this->semestre] ?? 'Semestre');
        });
    }

    // Relación de uno a muchos (periodo-examen)
    public function examenes()
    {
        return $this->hasMany(Examen::class, 'id_periodo', 'id_periodo');
    }
}
