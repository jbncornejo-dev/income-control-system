<?php

namespace Database\Seeders;

use App\Models\AuditoriaLog;
use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\ExamenAmbiente;
use App\Models\ExamenQr;
use App\Models\Grupo;
use App\Models\Habilitacion;
use App\Models\Incidencia;
use App\Models\Inscripcion;
use App\Models\Periodo;
use App\Models\RegistroIngreso;
use App\Models\TipoExamen;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * DemoExtraSeeder — agrega datos extra SIN borrar lo existente.
 *
 * Metas:
 *  - 5 asignaturas nuevas
 *  - 2 ambientes nuevos (Sala de Posgrado, Laboratorio 2)
 *  - Grupos nuevos para docente/docente2 en las asignaturas nuevas
 *  - 10 exámenes adicionales distribuidos en hoy, ayer y próximos días
 *  - El estudiante demo (id_estudiante=1) queda habilitado en TODOS
 *    los exámenes nuevos (y en los viejos si aún no tenía habilitación)
 *  - Registros de ingreso históricos del estudiante demo
 *  - Incidencias variadas para el demo
 *  - Logs de auditoría adicionales
 */
class DemoExtraSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $ahora   = Carbon::now(config('app.timezone', 'America/La_Paz'));
        $gestion = (string) $ahora->year;

        $admin = User::query()->where('activo', true)
            ->whereHas('rol', fn ($rol) => $rol->where('nombre_rol', 'administrador'))
            ->orderBy('id')->first();
        $docente = User::where('username', 'docente')->first();
        $docente2 = User::where('username', 'docente2')->first();
        $control = User::where('username', 'control')->first();

        if (! $admin || ! $docente || ! $control) {
            $this->command->warn('Crea el administrador con admin:provisionar y ejecuta el seeder general antes de DemoExtraSeeder.');
            return;
        }

        // ── 1. Asignaturas nuevas ──────────────────────────────────────────────
        $nuevasAsignaturas = [
            'Química Orgánica',
            'Bases de Datos I',
            'Inglés Técnico',
            'Economía General',
            'Electrónica Analógica',
        ];

        foreach ($nuevasAsignaturas as $nombre) {
            Asignatura::firstOrCreate(['nombre_asignatura' => $nombre]);
        }

        $asignaturas = Asignatura::pluck('id_asignatura', 'nombre_asignatura');

        // ── 2. Ambientes nuevos ────────────────────────────────────────────────
        $nuevosAmbientes = [
            ['Sala de Posgrado',  25],
            ['Laboratorio 2',     28],
            ['Aula 301',          38],
        ];

        foreach ($nuevosAmbientes as [$nombre, $cap]) {
            Ambiente::updateOrCreate(
                ['nombre_ambiente' => $nombre],
                ['capacidad'       => $cap]
            );
        }

        $ambientes = Ambiente::pluck('id_ambiente', 'nombre_ambiente');

        // ── 3. Período activo ──────────────────────────────────────────────────
        $periodo = Periodo::where('gestion', $gestion)->orderBy('numero')->first()
            ?? Periodo::firstOrCreate(
                ['gestion' => $gestion, 'tipo' => 'semestre', 'numero' => 1],
                ['fecha_inicio' => $gestion.'-01-01', 'fecha_fin' => $gestion.'-12-31']
            );

        // ── 4. Tipos de examen ─────────────────────────────────────────────────
        $tipos = TipoExamen::pluck('id_tipo_examen', 'nombre');

        // ── 5. Grupos nuevos para docente en las nuevas asignaturas ───────────
        $nuevosGruposDocente = [
            'Química Orgánica'     => ['A', 'B'],
            'Bases de Datos I'     => ['A', 'B'],
            'Inglés Técnico'       => ['A'],
            'Economía General'     => ['A', 'B'],
            'Electrónica Analógica'=> ['A'],
        ];

        $nuevosGruposDocente2 = [
            'Química Orgánica'      => ['C'],
            'Bases de Datos I'      => ['C'],
            'Electrónica Analógica' => ['B'],
        ];

        foreach ($nuevosGruposDocente as $asig => $grupos) {
            foreach ($grupos as $g) {
                Grupo::firstOrCreate(
                    ['id_asignatura' => $asignaturas[$asig], 'nombre_grupo' => $g, 'gestion' => $gestion],
                    ['id_usuario'    => $docente->id]
                );
            }
        }

        if ($docente2) {
            foreach ($nuevosGruposDocente2 as $asig => $grupos) {
                foreach ($grupos as $g) {
                    Grupo::firstOrCreate(
                        ['id_asignatura' => $asignaturas[$asig], 'nombre_grupo' => $g, 'gestion' => $gestion],
                        ['id_usuario'    => $docente2->id]
                    );
                }
            }
        }

        // ── 6. Exámenes adicionales ────────────────────────────────────────────
        // Distribución: pasados (ayer, hace 2 días), hoy, futuros (mañana, +2, +3)
        // para que el estudiante vea historial Y próximos en /mis-examenes.
        $definicion = [
            // ─ Pasados (estado NULL: el sistema los identifica por fecha) ─
            [
                'asignatura'       => 'Química Orgánica',
                'tipo'             => 'Primer Parcial',
                'dias'             => -2,
                'hora_inicio'      => '08:00',
                'duracion_minutos' => 90,
                'ambientes'        => ['Aula 301'],
                'grupos'           => ['A', 'B'],
                'normas'           => 'Calculadora no permitida. Tabla periódica incluida.',
                'estado'           => null,
            ],
            [
                'asignatura'       => 'Bases de Datos I',
                'tipo'             => 'Primer Parcial',
                'dias'             => -1,
                'hora_inicio'      => '10:00',
                'duracion_minutos' => 120,
                'ambientes'        => ['Laboratorio 2'],
                'grupos'           => ['A', 'B'],
                'normas'           => 'Consulta de apuntes permitida. Sin internet.',
                'estado'           => null,
            ],
            [
                'asignatura'       => 'Inglés Técnico',
                'tipo'             => 'Segundo Parcial',
                'dias'             => -1,
                'hora_inicio'      => '14:00',
                'duracion_minutos' => 60,
                'ambientes'        => ['Sala de Posgrado'],
                'grupos'           => ['A'],
                'normas'           => null,
                'estado'           => null,
            ],
            // ─ Hoy ─
            [
                'asignatura'       => 'Economía General',
                'tipo'             => 'Primer Parcial',
                'dias'             => 0,
                'hora_inicio'      => '09:00',
                'duracion_minutos' => 90,
                'ambientes'        => ['Aula 301'],
                'grupos'           => ['A', 'B'],
                'normas'           => 'Sin dispositivos electrónicos.',
                'estado'           => null,
            ],
            [
                'asignatura'       => 'Electrónica Analógica',
                'tipo'             => 'Primer Parcial',
                'dias'             => 0,
                'hora_inicio'      => '11:30',
                'duracion_minutos' => 120,
                'ambientes'        => ['Laboratorio 2', 'Sala de Posgrado'],
                'grupos'           => ['A', 'B'],
                'normas'           => 'Hoja de fórmulas permitida (una página A4, manuscrita).',
                'estado'           => null,
            ],
            [
                'asignatura'       => 'Química Orgánica',
                'tipo'             => 'Segundo Parcial',
                'dias'             => 0,
                'hora_inicio'      => '16:00',
                'duracion_minutos' => 90,
                'ambientes'        => ['Aula 301'],
                'grupos'           => ['A'],
                'normas'           => null,
                'estado'           => null,
            ],
            // ─ Futuros ─
            [
                'asignatura'       => 'Bases de Datos I',
                'tipo'             => 'Segundo Parcial',
                'dias'             => 1,
                'hora_inicio'      => '08:30',
                'duracion_minutos' => 120,
                'ambientes'        => ['Laboratorio 2'],
                'grupos'           => ['A', 'B', 'C'],
                'normas'           => 'Examen de modelo relacional y consultas SQL.',
                'estado'           => null,
            ],
            [
                'asignatura'       => 'Inglés Técnico',
                'tipo'             => 'Examen Final',
                'dias'             => 2,
                'hora_inicio'      => '10:00',
                'duracion_minutos' => 90,
                'ambientes'        => ['Sala de Posgrado'],
                'grupos'           => ['A'],
                'normas'           => 'Diccionario físico permitido.',
                'estado'           => null,
            ],
            [
                'asignatura'       => 'Economía General',
                'tipo'             => 'Examen Final',
                'dias'             => 3,
                'hora_inicio'      => '14:00',
                'duracion_minutos' => 120,
                'ambientes'        => ['Aula 301', 'Auditorio Central'],
                'grupos'           => ['A', 'B'],
                'normas'           => 'Presentar carnet universitario. No se admiten retrasos.',
                'estado'           => null,
            ],
            [
                'asignatura'       => 'Electrónica Analógica',
                'tipo'             => 'Examen Final',
                'dias'             => 4,
                'hora_inicio'      => '08:00',
                'duracion_minutos' => 150,
                'ambientes'        => ['Laboratorio 2'],
                'grupos'           => ['A', 'B'],
                'normas'           => null,
                'estado'           => null,
            ],
        ];

        $gruposPorAsignatura = Grupo::all()->groupBy('id_asignatura');

        $examenesCreados = collect();

        foreach ($definicion as $datos) {
            $fecha  = $ahora->copy()->addDays($datos['dias'])->toDateString();

            $examen = Examen::firstOrCreate(
                [
                    'id_asignatura' => $asignaturas[$datos['asignatura']],
                    'fecha'         => $fecha,
                    'hora_inicio'   => $datos['hora_inicio'],
                ],
                [
                    'id_periodo'       => $periodo->id_periodo,
                    'id_tipo_examen'   => $tipos[$datos['tipo']] ?? null,
                    'duracion_minutos' => $datos['duracion_minutos'],
                    'normas_generales' => $datos['normas'],
                    'estado'           => $datos['estado'],
                ]
            );

            foreach ($datos['ambientes'] as $nombreAmbiente) {
                if (isset($ambientes[$nombreAmbiente])) {
                    ExamenAmbiente::firstOrCreate([
                        'id_examen'    => $examen->id_examen,
                        'id_ambiente'  => $ambientes[$nombreAmbiente],
                    ]);
                }
            }

            // Vincular grupos
            $grupos = $gruposPorAsignatura
                ->get($examen->id_asignatura, collect())
                ->where('gestion', $gestion)
                ->whereIn('nombre_grupo', $datos['grupos'])
                ->values();

            if ($grupos->isNotEmpty()) {
                $examen->grupos()->syncWithoutDetaching($grupos->pluck('id_grupo')->all());
            }

            // Asignar personal de control
            $examen->personalControl()->syncWithoutDetaching([$control->id]);

            $examenesCreados->push($examen);
        }

        // ── 7. Estudiante demo + resto de estudiantes ──────────────────────────
        $estudianteDemo = Estudiante::orderBy('id_estudiante')->first(); // id=1
        $todosLosEstudiantes = Estudiante::orderBy('id_estudiante')->get();

        if (! $estudianteDemo) {
            return;
        }

        // ── 8. Inscripciones del estudiante demo en los grupos nuevos ──────────
        // Lo inscribimos al grupo A de cada nueva asignatura (grupo del docente).
        foreach ($nuevosGruposDocente as $asig => $grupos) {
            $grupo = Grupo::where('id_asignatura', $asignaturas[$asig])
                ->where('nombre_grupo', 'A')
                ->where('gestion', $gestion)
                ->first();

            if ($grupo) {
                Inscripcion::firstOrCreate([
                    'id_estudiante' => $estudianteDemo->id_estudiante,
                    'id_grupo'      => $grupo->id_grupo,
                ]);
            }
        }

        // ── 9. Habilitaciones del estudiante demo en TODOS los exámenes nuevos ─
        foreach ($examenesCreados as $examen) {
            Habilitacion::firstOrCreate(
                [
                    'id_estudiante' => $estudianteDemo->id_estudiante,
                    'id_examen'     => $examen->id_examen,
                ],
                [
                    'id_personal_control' => $control->id,
                    'estado_habilitado'   => true,
                ]
            );
            ExamenQr::paraHabilitacion(
                Habilitacion::where('id_estudiante', $estudianteDemo->id_estudiante)
                    ->where('id_examen', $examen->id_examen)
                    ->first()
            );
        }

        // Habilitamos al estudiante demo también en exámenes que ya existían
        // (los del seeder original) por si acaso no estaba en alguno.
        $examenesExistentes = Examen::whereNotIn('id_examen', $examenesCreados->pluck('id_examen'))->get();
        foreach ($examenesExistentes as $examen) {
            Habilitacion::firstOrCreate(
                [
                    'id_estudiante' => $estudianteDemo->id_estudiante,
                    'id_examen'     => $examen->id_examen,
                ],
                [
                    'id_personal_control' => $control->id,
                    'estado_habilitado'   => true,
                ]
            );
        }

        // ── 10. Repartir el resto de estudiantes en los nuevos exámenes ────────
        // Habilitamos a todos los demás estudiantes en los exámenes nuevos para
        // que las listas del docente/control se vean pobladas.
        foreach ($examenesCreados as $examen) {
            foreach ($todosLosEstudiantes->skip(1) as $index => $estudiante) {
                $habilitado = $index < ($todosLosEstudiantes->count() - 4); // últimos 3 inhabilitados
                Habilitacion::firstOrCreate(
                    [
                        'id_estudiante' => $estudiante->id_estudiante,
                        'id_examen'     => $examen->id_examen,
                    ],
                    [
                        'id_personal_control'  => $control->id,
                        'estado_habilitado'    => $habilitado,
                        'motivo_inhabilitacion' => $habilitado ? null : 'Deuda académica pendiente.',
                    ]
                );
            }
        }

        // ── 11. Registros de ingreso históricos del estudiante demo ────────────
        // Para los 3 exámenes pasados (fecha < hoy): lo marcamos como ingresado.
        $examenesFinalizados = $examenesCreados
            ->filter(fn ($e) => $e->fecha < $ahora->toDateString())
            ->values();
        foreach ($examenesFinalizados as $idx => $examen) {
            $ambiente = ExamenAmbiente::where('id_examen', $examen->id_examen)->first();
            if ($ambiente) {
                RegistroIngreso::firstOrCreate(
                    [
                        'id_estudiante'      => $estudianteDemo->id_estudiante,
                        'id_examen_ambiente' => $ambiente->id_examen_ambiente,
                    ],
                    [
                        'id_usuario'          => $control->id,
                        'fecha_hora_ingreso'  => Carbon::parse($examen->fecha . ' ' . $examen->hora_inicio)
                            ->addMinutes(5 + $idx * 2),
                    ]
                );
            }
        }

        // ── 12. Incidencias variadas para el demo ──────────────────────────────
        $tiposIncidencia = [
            ['tipo' => 'Problema de identificación',   'desc' => 'Demo extra: fotografía no coincide con documento presentado.'],
            ['tipo' => 'Intento de ingreso duplicado',  'desc' => 'Demo extra: estudiante ya registrado intentó ingresar nuevamente.'],
            ['tipo' => 'Documento vencido',             'desc' => 'Demo extra: carnet universitario con fecha de vencimiento expirada.'],
        ];

        foreach ($examenesCreados->take(3) as $idx => $examen) {
            $otraPersona = $todosLosEstudiantes->get($idx + 2);
            if (! $otraPersona) continue;
            Incidencia::firstOrCreate(
                [
                    'id_examen'      => $examen->id_examen,
                    'id_estudiante'  => $otraPersona->id_estudiante,
                    'tipo_incidencia'=> $tiposIncidencia[$idx]['tipo'],
                ],
                [
                    'id_usuario'         => $control->id,
                    'descripcion_motivo' => $tiposIncidencia[$idx]['desc'],
                    'fecha_hora'         => Carbon::parse($examen->fecha . ' ' . $examen->hora_inicio)
                        ->addMinutes(10 + $idx * 5),
                ]
            );
        }

        // ── 13. Logs de auditoría adicionales ─────────────────────────────────
        $accionesAuditoria = [
            ['tabla' => 'habilitacion', 'accion' => 'created', 'desc' => 'Habilitación masiva generada por seeder extra.'],
            ['tabla' => 'examen',       'accion' => 'created', 'desc' => 'Exámenes adicionales creados para demo.'],
            ['tabla' => 'incidencia',   'accion' => 'created', 'desc' => 'Incidencias de prueba registradas.'],
        ];

        foreach ($accionesAuditoria as $idx => $datos) {
            $registroRef = $examenesCreados->get($idx)?->id_examen ?? 1;
            AuditoriaLog::firstOrCreate(
                [
                    'id_usuario'           => $admin->id,
                    'tabla_afectada'       => $datos['tabla'],
                    'id_registro_afectado' => $registroRef,
                    'accion'               => $datos['accion'],
                ],
                [
                    'fecha_hora'   => $ahora->copy()->subMinutes(30 + $idx * 10),
                    'datos_nuevos' => ['fuente' => 'DemoExtraSeeder', 'nota' => $datos['desc']],
                ]
            );
        }
    }
}
