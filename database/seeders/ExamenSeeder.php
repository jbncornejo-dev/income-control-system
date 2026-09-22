<?php

namespace Database\Seeders;

use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\Examen;
use App\Models\ExamenAmbiente;
use App\Models\Grupo;
use App\Models\Periodo;
use App\Models\TipoExamen;
use Illuminate\Database\Seeder;

class ExamenSeeder extends Seeder
{
    public function run(): void
    {
        $asignaturas = Asignatura::pluck('id_asignatura', 'nombre_asignatura');
        $ambientes = Ambiente::pluck('id_ambiente', 'nombre_ambiente');
        $periodos = Periodo::all();
        $tipos = TipoExamen::pluck('id_tipo_examen', 'nombre');

        $examenes = [
            [
                'asignatura' => 'Cálculo II',
                'tipo' => 'Primer Parcial',
                'dias_desde_hoy' => 0,
                'hora_inicio' => '08:00',
                'duracion_minutos' => 120,
                'ambientes' => ['Aula 101', 'Aula 102'],
                'grupos' => ['A', 'B'],
                'normas_generales' => 'Calculadora científica permitida, sin apuntes.',
            ],
            [
                'asignatura' => 'Física I',
                'tipo' => 'Primer Parcial',
                'dias_desde_hoy' => 0,
                'hora_inicio' => '10:30',
                'duracion_minutos' => 90,
                'ambientes' => ['Aula 103', 'Aula 104'],
                'grupos' => ['A'],
                'normas_generales' => null,
            ],
            [
                'asignatura' => 'Programación I',
                'tipo' => 'Primer Parcial',
                'dias_desde_hoy' => 0,
                'hora_inicio' => '15:00',
                'duracion_minutos' => 150,
                'ambientes' => ['Laboratorio 1'],
                'grupos' => ['A', 'B'],
                'normas_generales' => 'Equipo asignado por sorteo. Consulte al personal de control.',
            ],
            [
                'asignatura' => 'Álgebra Lineal',
                'tipo' => 'Primer Parcial',
                'dias_desde_hoy' => 1,
                'hora_inicio' => '08:00',
                'duracion_minutos' => 120,
                'ambientes' => ['Aula 201'],
                'grupos' => ['A', 'B', 'C'],
                'normas_generales' => null,
            ],
            [
                'asignatura' => 'Estadística',
                'tipo' => 'Segundo Parcial',
                'dias_desde_hoy' => 1,
                'hora_inicio' => '14:00',
                'duracion_minutos' => 90,
                'ambientes' => ['Aula 202', 'Aula 101'],
                'grupos' => ['A'],
                'normas_generales' => 'Tablas estadísticas adjuntas al examen.',
            ],
            [
                'asignatura' => 'Fisiología',
                'tipo' => 'Examen Final',
                'dias_desde_hoy' => 2,
                'hora_inicio' => '09:00',
                'duracion_minutos' => 120,
                'ambientes' => ['Aula 103', 'Auditorio Central'],
                'grupos' => ['A'],
                'normas_generales' => null,
            ],
            [
                'asignatura' => 'Anatomía Humana',
                'tipo' => 'Segundo Parcial',
                'dias_desde_hoy' => 2,
                'hora_inicio' => '14:30',
                'duracion_minutos' => 150,
                'ambientes' => ['Aula 104'],
                'grupos' => ['A'],
                'normas_generales' => 'Sin dispositivos electrónicos durante la evaluación.',
            ],
            [
                'asignatura' => 'Redacción Académica',
                'tipo' => 'Examen Final',
                'dias_desde_hoy' => 3,
                'hora_inicio' => '08:00',
                'duracion_minutos' => 90,
                'ambientes' => ['Auditorio Central', 'Aula 201'],
                'grupos' => ['A'],
                'normas_generales' => null,
            ],
            [
                'asignatura' => 'Cálculo II',
                'tipo' => 'Segundo Parcial',
                'dias_desde_hoy' => 3,
                'hora_inicio' => '16:00',
                'duracion_minutos' => 120,
                'ambientes' => ['Aula 202'],
                'grupos' => ['A', 'B'],
                'normas_generales' => 'Segundo parcial. Presentación obligatoria de carnet universitario.',
            ],
            // Examen compartido de Programación I: el Examen Final lo rinden los
            // grupos A/B del primer docente y el C del segundo, en el Auditorio
            // Central. Es el escenario "compartido" que demuestra el listado
            // (badges por docente, filtro e ícono "Compartidos").
            [
                'asignatura' => 'Programación I',
                'tipo' => 'Examen Final',
                'dias_desde_hoy' => 1,
                'hora_inicio' => '16:30',
                'duracion_minutos' => 120,
                'ambientes' => ['Laboratorio 1', 'Auditorio Central'],
                'grupos' => ['A', 'B', 'C'],
                'normas_generales' => null,
            ],
            [
                'asignatura' => 'Cálculo II',
                'tipo' => 'Segundo Parcial',
                'dias_desde_hoy' => 2,
                'hora_inicio' => '11:00',
                'duracion_minutos' => 90,
                'ambientes' => ['Aula 201'],
                'grupos' => ['C'],
                'normas_generales' => null,
            ],
        ];

        // Grupos por asignatura: cada examen se vincula a los grupos que lo rinden
        // (listado en "grupos"; si no se indica, a todos los de su asignatura) para
        // que sea visible en el listado y en el dashboard del docente correspondiente
        // (el acceso se filtra por el pivot examen_grupo).
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
                    'id_tipo_examen' => $tipos[$datos['tipo']] ?? null,
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

            // Vincula los grupos del examen (sync idempotente): sin esto
            // el examen queda fuera del filtro por grupos que usa el listado.
            // Se restringe a la gestión en curso: los nombres de grupo se
            // repiten entre gestiones y un examen no cubre grupos de otra.
            $grupos = $gruposPorAsignatura
                ->get($examen->id_asignatura, collect())
                ->where('gestion', $gestion)
                ->when(isset($datos['grupos']), function ($grupos) use ($datos) {
                    return $grupos->whereIn('nombre_grupo', $datos['grupos']);
                })
                ->values();

            if ($grupos->isNotEmpty()) {
                $examen->grupos()->sync($grupos->pluck('id_grupo')->all());
            }
        }
    }
}
