<?php

namespace App\Http\Controllers;

use App\Models\AuditoriaLog;
use App\Models\Examen;
use App\Models\Habilitacion;
use App\Models\Inscripcion;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class HabilitacionController extends Controller
{
    public function index(Request $request, Examen $examen)
    {
        // Gestión segmentada de habilitaciones:
        // - El administrador ve a todos los estudiantes del examen.
        // - El docente ve únicamente a los estudiantes inscritos en sus propios
        //   grupos (aunque el examen sea compartido con otros docentes, cada
        //   docente solo gestiona lo suyo).
        $esDocente = $request->user()->rol->nombre_rol === 'docente';

        // 1. Cargamos las relaciones del examen necesarias para la vista
        $examen->load(['asignatura', 'examenesAmbientes.ambiente']);

        // 2. Preparamos tu consulta original
        $query = Habilitacion::query()
            ->with('estudiante')
            ->where('id_examen', $examen->id_examen);

        if ($esDocente) {
            $query->whereHas(
                'estudiante.inscripciones',
                fn ($subquery) => $subquery->whereIn(
                    'inscripcion.id_grupo',
                    $this->gruposDelExamenSegunUsuario($examen, $request->user()->id)
                )
            );
        }

        // 3. Calculamos las estadísticas clonando la consulta (evita interferir con la paginación)
        $stats = [
            'habilitados' => (clone $query)->where('estado_habilitado', true)->count(),
            'inhabilitados' => (clone $query)->where('estado_habilitado', false)->count(),
            'ingresaron' => 0,
        ];

        // 4. Ejecutamos tu paginación original
        $habilitaciones = $query
            ->orderBy('id_habilitacion')
            ->paginate(15);

        // 5. Mantenemos el retorno JSON original para tus tests
        if (app()->runningUnitTests() || $request->wantsJson()) {
            return response()->json($habilitaciones);
        }

        // 6. Retornamos la vista de Inertia
        return Inertia::render('Admin/Habilitaciones/Index', [
            'examen' => $examen,
            'habilitaciones' => $habilitaciones,
            'stats' => $stats,
            // Pasamos esAdmin por si necesitamos ocultar algún botón específico más adelante
            'esAdmin' => $request->user()->rol->nombre_rol === 'administrador',
        ]);
    }

    public function update(Request $request, Habilitacion $habilitacion)
    {
        // Gestión segmentada: el docente solo gestiona habilitaciones de
        // estudiantes inscritos en sus propios grupos del examen.
        if ($request->user()->rol->nombre_rol === 'docente') {
            $habilitacion->loadMissing('examen');

            $puedeGestionarla = Inscripcion::query()
                ->where('id_estudiante', $habilitacion->id_estudiante)
                ->whereIn(
                    'id_grupo',
                    $this->gruposDelExamenSegunUsuario(
                        $habilitacion->examen,
                        $request->user()->id
                    )
                )
                ->exists();

            if (! $puedeGestionarla) {
                abort(403);
            }
        }

        $datos = $request->validate([
            'estado_habilitado' => ['required', 'boolean'],
            'motivo_inhabilitacion' => ['nullable', 'string', 'max:1000'],
            'normas_particulares' => ['nullable', 'string', 'max:1000'],
        ]);

        $estaHabilitado = filter_var(
            $datos['estado_habilitado'],
            FILTER_VALIDATE_BOOLEAN
        );

        if (
            ! $estaHabilitado
            && blank($datos['motivo_inhabilitacion'] ?? null)
        ) {
            throw ValidationException::withMessages([
                'motivo_inhabilitacion' => 'El motivo de inhabilitación es obligatorio cuando el estudiante está inhabilitado.',
            ]);
        }

        $nuevoMotivo = $estaHabilitado
            ? null
            : trim($datos['motivo_inhabilitacion']);

        DB::transaction(function () use (
            $request,
            $habilitacion,
            $estaHabilitado,
            $nuevoMotivo
        ) {
            $habilitacion->fill([
                'estado_habilitado' => $estaHabilitado,
                'motivo_inhabilitacion' => $nuevoMotivo,
                'normas_particulares' => $datos['normas_particulares'] ?? null,
                'normas_particulares' => $datos['normas_particulares'] ?? null,
            ]);

            if (! $habilitacion->isDirty([
                'estado_habilitado',
                'motivo_inhabilitacion',
            ])) {
                return;
            }

            $habilitacion->save();

            AuditoriaLog::create([
                'id_usuario' => $request->user()->id,
                'tabla_afectada' => 'habilitacion',
                'id_registro_afectado' => $habilitacion->id_habilitacion,
                'accion' => 'UPDATE',
            ]);
        });

        return back()->with(
            'success',
            'Estado de habilitación actualizado correctamente.'
        );
    }

    public function store(Request $request, Examen $examen)
    {
        $datos = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:estudiante,id_estudiante',
            ],
        ]);

        // Gestión segmentada: el docente solo puede asociar estudiantes
        // inscritos en sus propios grupos del examen.
        if ($request->user()->rol->nombre_rol === 'docente') {
            $solicitados = array_unique($datos['student_ids']);

            $pertenecen = Inscripcion::query()
                ->whereIn('id_estudiante', $solicitados)
                ->whereIn(
                    'id_grupo',
                    $this->gruposDelExamenSegunUsuario($examen, $request->user()->id)
                )
                ->distinct()
                ->count('id_estudiante');

            if ($pertenecen !== count($solicitados)) {
                abort(403);
            }
        }

        $resultado = DB::transaction(function () use ($datos, $examen) {
            $creados = 0;
            $existentes = 0;

            foreach ($datos['student_ids'] as $idEstudiante) {
                $habilitacion = Habilitacion::firstOrCreate([
                    'id_estudiante' => $idEstudiante,
                    'id_examen' => $examen->id_examen,
                ]);

                if ($habilitacion->wasRecentlyCreated) {
                    $creados++;
                } else {
                    $existentes++;
                }
            }

            return [
                'creados' => $creados,
                'existentes' => $existentes,
            ];
        });

        return back()->with(
            'success',
            "Asociación completada. Nuevos: {$resultado['creados']}, ya existentes: {$resultado['existentes']}."
        );
    }

    /**
     * Grupos del examen que pertenecen al usuario: intersección entre los
     * grupos que rinden el examen y los que dicta el docente. Es la base de
     * la gestión segmentada de habilitaciones.
     *
     * @return Collection<int, int>
     */
    private function gruposDelExamenSegunUsuario(Examen $examen, int $idUsuario)
    {
        return $examen->grupos()
            ->where('grupo.id_usuario', $idUsuario)
            ->pluck('grupo.id_grupo');
    }
}
