<?php

namespace Database\Seeders;

use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Habilitacion;
use Illuminate\Database\Seeder;

class HabilitacionSeeder extends Seeder
{
    public function run(): void
    {
        $estudiantes = Estudiante::pluck('id_estudiante', 'codigo_universitario');
        $examenes = Examen::with(['asignatura:id_asignatura,nombre_asignatura'])
            ->get()
            ->keyBy(fn ($examen) => $examen->fecha.'|'.$examen->hora_inicio);

        $libres = $estudiantes->take(27);
        $inhabilitados = $estudiantes->slice(27);

        $examenesParaTodos = $examenes->take(3)->values();

        foreach ($libres as $codigo => $idEstudiante) {
            foreach ($examenesParaTodos as $examen) {
                Habilitacion::firstOrCreate(
                    ['id_estudiante' => $idEstudiante, 'id_examen' => $examen->id_examen],
                    ['estado_habilitado' => true]
                );
            }
        }

        foreach ($inhabilitados as $codigo => $idEstudiante) {
            Habilitacion::firstOrCreate(
                ['id_estudiante' => $idEstudiante, 'id_examen' => $examenesParaTodos[0]->id_examen],
                [
                    'estado_habilitado' => false,
                    'motivo_inhabilitacion' => 'Deuda pendiente de pago en biblioteca',
                ]
            );
        }

        // Examen compartido de Programación I (grupos A/B/C, Examen Final):
        // los 30 estudiantes del demo están habilitados. Cada docente ve su
        // propio segmento según la matrícula (InscripcionSeeder); el
        // administrador los ve todos.
        $examenCompartido = $examenes
            ->first(fn (Examen $examen) => $examen->asignatura?->nombre_asignatura === 'Programación I'
                && $examen->grupos()->pluck('grupo.id_usuario')->filter()->unique()->count() > 1);

        if ($examenCompartido) {
            foreach ($estudiantes as $codigo => $idEstudiante) {
                Habilitacion::firstOrCreate(
                    ['id_estudiante' => $idEstudiante, 'id_examen' => $examenCompartido->id_examen],
                    ['estado_habilitado' => true]
                );
            }
        }
    }
}
