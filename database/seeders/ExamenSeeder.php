<?php

namespace Database\Seeders;

use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\Examen;
use App\Models\ExamenAmbiente;
use App\Models\Grupo;
use App\Models\Periodo;
use Illuminate\Database\Seeder;

class ExamenSeeder extends Seeder
{
    public function run(): void
    {
        $asignaturas = Asignatura::pluck('id_asignatura', 'nombre_asignatura');
        $ambientes = Ambiente::pluck('id_ambiente', 'nombre_ambiente');
        $periodos = Periodo::all();

        $examenes = [
            [
                'asignatura' => 'Cálculo II',
                'dias_desde_hoy' => 0,
                'hora_inicio' => '08:00',
                'duracion_minutos' => 120,
                'ambientes' => ['Aula 101', 'Aula 102'],
                'normas_generales' => 'Calculadora científica permitida, sin apuntes.',
            ],
            [
                'asignatura' => 'Física I',
                'dias_desde_hoy' => 0,
                'hora_inicio' => '10:30',
                'duracion_minutos' => 90,
                'ambientes' => ['Aula 103', 'Aula 104'],
                'normas_generales' => null,
            ],
            [
                'asignatura' => 'Programación I',
                'dias_desde_hoy' => 0,
                'hora_inicio' => '15:00',
                'duracion_minutos' => 150,
                'ambientes' => ['Laboratorio 1'],
                'normas_generales' => 'Equipo asignado por sorteo. Consulte al personal de control.',
            ],
            [
                'asignatura' => 'Álgebra Lineal',
                'dias_desde_hoy' => 1,
                'hora_inicio' => '08:00',
                'duracion_minutos' => 120,
                'ambientes' => ['Aula 201'],
                'normas_generales' => null,
            ],
            [
                'asignatura' => 'Estadística',
                'dias_desde_hoy' => 1,
                'hora_inicio' => '14:00',
                'duracion_minutos' => 90,
                'ambientes' => ['Aula 202', 'Aula 101'],
                'normas_generales' => 'Tablas estadísticas adjuntas al examen.',
            ],
            [
                'asignatura' => 'Fisiología',
                'dias_desde_hoy' => 2,
                'hora_inicio' => '09:00',
                'duracion_minutos' => 120,
                'ambientes' => ['Aula 103', 'Auditorio Central'],
                'normas_generales' => null,
            ],
            [
                'asignatura' => 'Anatomía Humana',
                'dias_desde_hoy' => 2,
                'hora_inicio' => '14:30',
                'duracion_minutos' => 150,
                'ambientes' => ['Aula 104'],
                'normas_generales' => 'Sin dispositivos electrónicos durante la evaluación.',
            ],
            [
                'asignatura' => 'Redacción Académica',
                'dias_desde_hoy' => 3,
                'hora_inicio' => '08:00',
                'duracion_minutos' => 90,
                'ambientes' => ['Auditorio Central', 'Aula 201'],
                'normas_generales' => null,
            ],
            [
                'asignatura' => 'Cálculo II',
                'dias_desde_hoy' => 3,
                'hora_inicio' => '16:00',
                'duracion_minutos' => 120,
                'ambientes' => ['Aula 202'],
                'normas_generales' => 'Segundo parcial. Presentación obligatoria de carnet universitario.',
            ],
        ];

        // Grupos por asignatura: cada examen seedeado se vincula a todos los
        // grupos de su asignatura para que sea visible en el listado y en el
        // dashboard del docente (el acceso se filtra por el pivot examen_grupo).
        $gruposPorAsignatura = Grupo::all()->groupBy('id_asignatura');

        $gestion = (string) now()->year;

        foreach ($examenes as $datos) {
            // Periodo sugerido para la fecha del examen: el que contiene la fecha;
            // si queda fuera de rango (p. ej. examen de fin de gestión), el primero
            // de la gestión correspondiente. Si no existe, se crea el del primer periodo.
            $fecha = now()->addDays($datos['dias_desde_hoy'])->toDateString();
            $periodo = $periodos
                ->where('gestion', $gestion)
                ->first(fn (Periodo $p) => $p->fecha_inicio !== null
                    && $p->fecha_fin !== null
                    && $p->fecha_inicio <= $fecha
                    && $p->fecha_fin >= $fecha)
                ?? $periodos
                    ->where('gestion', $gestion)
                    ->sortBy('numero')
                    ->first()
                ?? Periodo::firstOrCreate(
                    ['gestion' => $gestion, 'tipo' => 'semestre', 'numero' => 1],
                    ['fecha_inicio' => $gestion.'-01-01', 'fecha_fin' => $gestion.'-12-31']
                );

            $examen = Examen::firstOrCreate(
                [
                    'id_asignatura' => $asignaturas[$datos['asignatura']],
                    'fecha' => $fecha,
                    'hora_inicio' => $datos['hora_inicio'],
                ],
                [
                    'id_periodo' => $periodo->id_periodo,
                    'duracion_minutos' => $datos['duracion_minutos'],
                    'normas_generales' => $datos['normas_generales'],
                ]
            );

            foreach ($datos['ambientes'] as $nombreAmbiente) {
                ExamenAmbiente::firstOrCreate([
                    'id_examen' => $examen->id_examen,
                    'id_ambiente' => $ambientes[$nombreAmbiente],
                ]);
            }

            // Vincula los grupos de la asignatura (sync idempotente): sin esto
            // el examen queda fuera del filtro por grupos que usa el listado.
            $grupos = $gruposPorAsignatura->get($examen->id_asignatura, collect());

            if ($grupos->isNotEmpty()) {
                $examen->grupos()->sync($grupos->pluck('id_grupo')->all());
            }
        }
    }
}
