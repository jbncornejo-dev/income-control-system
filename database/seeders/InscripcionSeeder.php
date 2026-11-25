<?php

namespace Database\Seeders;

use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Grupo;
use App\Models\Inscripcion;
use Illuminate\Database\Seeder;

class InscripcionSeeder extends Seeder
{
    /**
     * Matrícula del demo: los 30 estudiantes de desarrollo se reparten por
     * igual entre los grupos A, B y C de Programación I (A/B del primer
     * docente, C del segundo). Así el examen compartido de esa asignatura
     * muestra un segmento de habilitaciones por cada docente al consultar
     * el detalle.
     */
    public function run(): void
    {
        $gestion = (string) now()->year;

        $grupos = Grupo::whereHas('asignatura', fn ($q) => $q->where('nombre_asignatura', 'Programación I'))
            ->where('gestion', $gestion)
            ->get()
            ->keyBy('nombre_grupo');

        if ($grupos->isEmpty()) {
            return;
        }

        $estudiantes = Estudiante::orderBy('id_estudiante')->get();

        $porGrupo = [
            'A' => $estudiantes->slice(0, 10),
            'B' => $estudiantes->slice(10, 10),
            'C' => $estudiantes->slice(20, 10),
        ];

        foreach ($porGrupo as $nombreGrupo => $lote) {
            $grupo = $grupos->get($nombreGrupo);

            if (! $grupo) {
                continue;
            }

            foreach ($lote as $estudiante) {
                Inscripcion::firstOrCreate([
                    'id_estudiante' => $estudiante->id_estudiante,
                    'id_grupo' => $grupo->id_grupo,
                ]);
            }
        }
    }
}
