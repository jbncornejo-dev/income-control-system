<?php

namespace Database\Seeders;

use App\Models\Examen;
use App\Models\Estudiante;
use App\Models\Incidencia;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoIncidenciasSeeder extends Seeder
{
    public function run(): void
    {
        $control = User::where('username', 'control')->first();
        $estudiantes = Estudiante::query()->orderBy('id_estudiante')->get();
        $examenes = Examen::query()
            ->where(function ($query) {
                $query->whereNull('estado')
                    ->orWhereNotIn('estado', ['cancelado', 'anulado']);
            })
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->take(6)
            ->get();

        if (! $control || $estudiantes->count() < 6 || $examenes->count() < 3) {
            return;
        }

        $casos = [
            ['tipo' => 'Problema de identificación', 'dia' => 0, 'estudiante' => 1, 'examen' => 0],
            ['tipo' => 'Problema de identificación', 'dia' => 0, 'estudiante' => 2, 'examen' => 0],
            ['tipo' => 'Intento indebido', 'dia' => 0, 'estudiante' => 3, 'examen' => 0],
            ['tipo' => 'Cambio de ambiente', 'dia' => -1, 'estudiante' => 4, 'examen' => 1],
            ['tipo' => 'Otro', 'dia' => -1, 'estudiante' => 5, 'examen' => 1],
            ['tipo' => 'Expulsión', 'dia' => -2, 'estudiante' => 6, 'examen' => 2],
            ['tipo' => 'Problema de identificación', 'dia' => -2, 'estudiante' => 7, 'examen' => 2],
            ['tipo' => 'Cambio de ambiente', 'dia' => -3, 'estudiante' => 8, 'examen' => 3],
            ['tipo' => 'Otro', 'dia' => -3, 'estudiante' => 9, 'examen' => 3],
            ['tipo' => 'Intento indebido', 'dia' => -4, 'estudiante' => 10, 'examen' => 4],
            ['tipo' => 'Problema de identificación', 'dia' => -4, 'estudiante' => 11, 'examen' => 4],
            ['tipo' => 'Expulsión', 'dia' => -5, 'estudiante' => 12, 'examen' => 5],
        ];

        foreach ($casos as $caso) {
            $examen = $examenes->get($caso['examen']);
            $estudiante = $estudiantes->get($caso['estudiante']);

            if (! $examen || ! $estudiante) {
                continue;
            }

            Incidencia::firstOrCreate(
                [
                    'id_examen' => $examen->id_examen,
                    'id_estudiante' => $estudiante->id_estudiante,
                    'tipo_incidencia' => $caso['tipo'],
                ],
                [
                    'id_usuario' => $control->id,
                    'descripcion_motivo' => 'Registro demo para probar filtros y estadísticas de incidencias.',
                    'fecha_hora' => now()->addDays($caso['dia'])->setTime(10 + ($caso['estudiante'] % 8), 15),
                ]
            );
        }
    }
}
