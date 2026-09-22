<?php

namespace App\Http\Controllers;

use App\Models\AuditoriaLog;
use App\Models\Examen;
use App\Models\Grupo;
use App\Models\Habilitacion;
use App\Models\Inscripcion;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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

        $filtros = $request->validate([
            'estado' => ['nullable', Rule::in(['todos', 'habilitados', 'inhabilitados'])],
            'busqueda' => ['nullable', 'string', 'max:100'],
        ]);
        $estado = $filtros['estado'] ?? 'todos';
        $busqueda = trim($filtros['busqueda'] ?? '');

        // 1. Cargamos las relaciones del examen necesarias para la vista
        $examen->load(['asignatura', 'examenesAmbientes.ambiente']);

        $base = $this->consultaFiltrada($examen, $busqueda);

        if ($esDocente) {
            // El docente solo ve y gestiona a los estudiantes inscritos en sus
            // propios grupos del examen (segmentación por grupos).
            $base->whereHas(
                'estudiante.inscripciones',
                fn ($subquery) => $subquery->whereIn(
                    'inscripcion.id_grupo',
                    $this->gruposDelExamenSegunUsuario($examen, $request->user()->id)
                )
            );
        }

        // 3. Calculamos las estadísticas clonando la consulta (evita interferir con la paginación)
        $stats = [
            'habilitados' => (clone $base)->where('estado_habilitado', true)->count(),
            'inhabilitados' => (clone $base)->where('estado_habilitado', false)->count(),
            'ingresaron' => (clone $base)->whereHas('estudiante.registrosIngreso.examenAmbiente', fn ($q) => $q->where('id_examen', $examen->id_examen))->count(),
        ];

        $query = clone $base;
        if ($estado !== 'todos') {
            $query->where('estado_habilitado', $estado === 'habilitados');
        }
        $habilitaciones = $query->with(['estudiante' => fn ($q) => $q->withExists(['registrosIngreso as ya_ingreso' => fn ($r) => $r->whereHas('examenAmbiente', fn ($a) => $a->where('id_examen', $examen->id_examen))])])
            ->orderBy('id_habilitacion')
            ->paginate(15)->withQueryString();

        // 5. Mantenemos el retorno JSON original para tus tests
        if (app()->runningUnitTests() || $request->wantsJson()) {
            return response()->json($habilitaciones);
        }

        // 6. Retornamos la vista de Inertia
        return Inertia::render('Admin/Habilitaciones/Index', [
            'examen' => $examen,
            'habilitaciones' => $habilitaciones,
            'stats' => $stats,
            'filtros' => ['estado' => $estado, 'busqueda' => $busqueda],
            'coincidencias' => $habilitaciones->total(),
            'acciones' => [
                'habilitar' => (clone $base)->where('estado_habilitado', false)->whereDoesntHave('estudiante.registrosIngreso.examenAmbiente', fn ($q) => $q->where('id_examen', $examen->id_examen))->count(),
                'inhabilitar' => (clone $base)->where('estado_habilitado', true)->whereDoesntHave('estudiante.registrosIngreso.examenAmbiente', fn ($q) => $q->where('id_examen', $examen->id_examen))->count(),
            ],
            // Pasamos esAdmin por si necesitamos ocultar algún botón específico más adelante
            'esAdmin' => $request->user()->rol->nombre_rol === 'administrador',
        ]);
    }

    public function update(Request $request, Habilitacion $habilitacion)
    {
        $this->autorizarExamen($request, $habilitacion->examen);

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
            $nuevoMotivo,
            $datos
        ) {
            $this->asegurarSinIngreso($habilitacion);
            $habilitacion->fill([
                'estado_habilitado' => $estaHabilitado,
                'motivo_inhabilitacion' => $nuevoMotivo,
                'normas_particulares' => $datos['normas_particulares'] ?? null,
            ]);

            if (! $habilitacion->isDirty([
                'estado_habilitado',
                'motivo_inhabilitacion',
                'normas_particulares',
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

    public function updateBulk(Request $request, Examen $examen)
    {
        $this->autorizarExamen($request, $examen);
        $datos = $request->validate([
            'estado_habilitado' => ['required', 'boolean'],
            'motivo_inhabilitacion' => ['required_if:estado_habilitado,false', 'nullable', 'string', 'max:1000'],
            'estado' => ['required', Rule::in(['habilitados', 'inhabilitados'])],
            'busqueda' => ['nullable', 'string', 'max:100'],
        ]);
        $habilitar = filter_var($datos['estado_habilitado'], FILTER_VALIDATE_BOOLEAN);
        if (($habilitar && $datos['estado'] !== 'inhabilitados') || (! $habilitar && $datos['estado'] !== 'habilitados')) {
            throw ValidationException::withMessages(['estado' => 'La acción no corresponde al filtro seleccionado.']);
        }
        if (! $habilitar && blank($datos['motivo_inhabilitacion'] ?? null)) {
            throw ValidationException::withMessages(['motivo_inhabilitacion' => 'El motivo de inhabilitación es obligatorio.']);
        }
        $estado = $datos['estado'];
        $query = $this->consultaFiltrada($examen, trim($datos['busqueda'] ?? ''));
        if ($estado !== 'todos') {
            $query->where('estado_habilitado', $estado === 'habilitados');
        }
        $query->where('estado_habilitado', ! $habilitar);
        $total = (clone $query)->count();
        $query->whereDoesntHave('estudiante.registrosIngreso.examenAmbiente', fn ($q) => $q->where('id_examen', $examen->id_examen));

        $actualizados = DB::transaction(function () use ($query, $request, $habilitar, $datos) {
            $cantidad = 0;
            foreach ($query->orderBy('id_habilitacion')->cursor() as $habilitacion) {
                $habilitacion->update([
                    'estado_habilitado' => $habilitar,
                    'motivo_inhabilitacion' => $habilitar ? null : trim($datos['motivo_inhabilitacion']),
                ]);
                AuditoriaLog::create([
                    'id_usuario' => $request->user()->id,
                    'tabla_afectada' => 'habilitacion',
                    'id_registro_afectado' => $habilitacion->id_habilitacion,
                    'accion' => 'UPDATE',
                ]);
                $cantidad++;
            }

            return $cantidad;
        });

        return back()->with('success', "Actualizados: {$actualizados}. Omitidos por ingreso registrado: ".($total - $actualizados).'.');
    }

    private function consultaFiltrada(Examen $examen, string $busqueda)
    {
        $query = Habilitacion::where('id_examen', $examen->id_examen);
        if ($busqueda !== '') {
            $query->whereHas('estudiante', function ($q) use ($busqueda) {
                $q->where(function ($q) use ($busqueda) {
                    $q->whereRaw('LOWER(nombres) LIKE ?', ['%'.mb_strtolower($busqueda).'%'])
                        ->orWhereRaw('LOWER(apellidos) LIKE ?', ['%'.mb_strtolower($busqueda).'%'])
                        ->orWhereRaw("LOWER(nombres || ' ' || apellidos) LIKE ?", ['%'.mb_strtolower($busqueda).'%'])
                        ->orWhere('codigo_universitario', 'ilike', '%'.$busqueda.'%');
                });
            });
        }

        return $query;
    }

    private function autorizarExamen(Request $request, Examen $examen): void
    {
        if ($request->user()->rol->nombre_rol === 'docente' && ! Grupo::where('id_usuario', $request->user()->id)->where('id_asignatura', $examen->id_asignatura)->exists()) {
            abort(403);
        }
    }

    private function asegurarSinIngreso(Habilitacion $habilitacion): void
    {
        if ($habilitacion->estudiante()->whereHas('registrosIngreso.examenAmbiente', fn ($q) => $q->where('id_examen', $habilitacion->id_examen))->exists()) {
            throw ValidationException::withMessages(['estado_habilitado' => 'No se puede modificar: el estudiante ya ingresó al examen.']);
        }
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
