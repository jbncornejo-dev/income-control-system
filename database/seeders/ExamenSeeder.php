<?php

namespace Database\Seeders;

use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\Examen;
use App\Models\ExamenAmbiente;
use Illuminate\Database\Seeder;

class ExamenSeeder extends Seeder
{
    public function run(): void
    {
        $asignaturas = Asignatura::pluck('id_asignatura', 'nombre_asignatura');
        $ambientes = Ambiente::pluck('id_ambiente', 'nombre_ambiente');

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

        foreach ($examenes as $datos) {
            $examen = Examen::firstOrCreate(
                [
                    'id_asignatura' => $asignaturas[$datos['asignatura']],
                    'fecha' => now()->addDays($datos['dias_desde_hoy'])->toDateString(),
                    'hora_inicio' => $datos['hora_inicio'],
                ],
                [
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
        }
    }
}
