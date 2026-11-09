<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ExamenQr extends Model
{
    use HasFactory;

    protected $table = 'examen_qr';
    protected $primaryKey = 'id_examen_qr';

    protected $fillable = [
        'id_habilitacion',
        'id_estudiante',
        'id_examen',
        'token',
        'metadata',
        'expires_at',
        'invalidated_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'expires_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    public function habilitacion()
    {
        return $this->belongsTo(Habilitacion::class, 'id_habilitacion', 'id_habilitacion');
    }

    public function estudiante()
    {
        return $this->belongsTo(Estudiante::class, 'id_estudiante', 'id_estudiante');
    }

    public function examen()
    {
        return $this->belongsTo(Examen::class, 'id_examen', 'id_examen');
    }

    public function estaVigente(): bool
    {
        return $this->invalidated_at === null && now()->lte($this->expires_at);
    }

    public static function paraHabilitacion(Habilitacion $habilitacion): self
    {
        $habilitacion->loadMissing('examen');
        $examen = $habilitacion->examen;
        $inicio = Carbon::parse($examen->fecha.' '.$examen->hora_inicio);

        $actual = self::where('id_habilitacion', $habilitacion->id_habilitacion)
            ->whereNull('invalidated_at')
            ->latest('id_examen_qr')
            ->first();

        if ($actual && $actual->expires_at?->isFuture()) {
            return $actual;
        }

        if ($actual) {
            $actual->update(['invalidated_at' => now()]);
        }

        return self::create([
                'id_habilitacion' => $habilitacion->id_habilitacion,
                'id_estudiante' => $habilitacion->id_estudiante,
                'id_examen' => $habilitacion->id_examen,
                'token' => 'examen:v1:'.Str::random(64),
                'metadata' => [
                    'id_examen' => $examen->id_examen,
                    'fecha' => $examen->fecha,
                    'hora_inicio' => $examen->hora_inicio,
                    'duracion_minutos' => (int) $examen->duracion_minutos,
                    'expires_at' => $inicio->copy()->addMinutes((int) $examen->duracion_minutos)->toIso8601String(),
                ],
                'expires_at' => $inicio->copy()->addMinutes((int) $examen->duracion_minutos),
            ]);
    }
}
