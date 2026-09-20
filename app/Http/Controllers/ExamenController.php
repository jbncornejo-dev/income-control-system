<?php

namespace App\Http\Controllers;

use App\Http\Requests\DisponibilidadExamenRequest;
use App\Http\Requests\IndexExamenRequest;
use App\Http\Requests\StoreExamenRequest;
use App\Http\Requests\UpdateExamenRequest;
use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\Examen;
use App\Models\ExamenAmbiente;
use App\Models\Grupo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ExamenController extends Controller
{
    public function index(IndexExamenRequest $request)
    {
        $filtros = $request->validated();
        $esDocente = auth()->user()->rol->nombre_rol === 'docente';

        $query = Examen::query()
            ->select(['id_examen', 'id_asignatura', 'fecha', 'hora_inicio', 'duracion_minutos', 'normas_generales'])
            ->with([
                'asignatura' => fn ($subquery) => $subquery->select(['id_asignatura', 'nombre_asignatura']),
                'examenesAmbientes.ambiente' => fn ($subquery) => $subquery->select(['id_ambiente', 'nombre_ambiente']),
            ]);

        if ($esDocente) {
            // El docente solo ve los exámenes de las asignaturas que dicta (sus grupos).
            $query->whereHas('asignatura.grupos', function ($subquery) {
                $subquery->where('grupo.id_usuario', auth()->id());
            });

            // Contexto: solo se cargan los grupos del docente en cada asignatura.
            $query->with(['asignatura.grupos' => fn ($subquery) => $subquery
                ->where('id_usuario', auth()->id())
                ->select(['id_grupo', 'id_asignatura', 'id_usuario', 'nombre_grupo'])]);
        } else {
            // Contexto para el administrador: grupos y docentes de cada asignatura.
            $query->with([
                'asignatura.grupos' => fn ($subquery) => $subquery->select(['id_grupo', 'id_asignatura', 'id_usuario', 'nombre_grupo']),
                'asignatura.grupos.usuario' => fn ($subquery) => $subquery->select(['id', 'name']),
            ]);
        }

        if (isset($filtros['asignatura'])) {
            // Búsqueda tolerante a mayúsculas/minúsculas y a acentos: se normaliza
            // con unaccent() + lower() en ambos lados, así "CALCULO" o "calculo"
            // encuentran la asignatura "Cálculo". Los comodines escritos por el
            // usuario se tratan como literales.
            $nombre = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filtros['asignatura']);
            $query->whereHas('asignatura', function ($subquery) use ($nombre) {
                $subquery->whereRaw('unaccent(lower(nombre_asignatura)) LIKE unaccent(lower(?))', ['%'.$nombre.'%']);
            });
        }

        if (isset($filtros['fecha'])) {
            $query->where('fecha', $filtros['fecha']);
        }

        if (isset($filtros['hora_inicio'])) {
            $query->where('hora_inicio', $filtros['hora_inicio']);
        }

        $examenes = $query
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->orderBy('id_examen')
            ->paginate(15)
            ->appends($filtros)
            ->through(function (Examen $examen) use ($esDocente) {
                $grupos = $examen->asignatura?->grupos ?? collect();

                // Nombres de grupos como array plano, sin duplicados.
                $examen->setAttribute('grupos', $grupos
                    ->pluck('nombre_grupo')
                    ->unique()
                    ->values()
                    ->all());

                if (! $esDocente) {
                    $examen->setAttribute('docentes', $grupos
                        ->pluck('usuario.name')
                        ->filter()
                        ->unique()
                        ->values()
                        ->all());
                }

                // El detalle de grupos ya se expone en "grupos"; no se repite anidado.
                $examen->asignatura?->makeHidden('grupos');

                return $examen;
            });

        $filtrosVista = [
            'asignatura' => $filtros['asignatura'] ?? null,
            'fecha' => $filtros['fecha'] ?? null,
            'hora_inicio' => $filtros['hora_inicio'] ?? null,
        ];

        if (app()->runningUnitTests() || $request->wantsJson()) {
            return response()->json([
                'examenes' => $examenes,
                'filtros' => $filtrosVista,
            ]);
        }

        // Determinamos la vista según el rol del usuario autenticado
        $vista = auth()->user()->rol->nombre_rol === 'docente'
            ? 'Docente/Examenes/Index'
            : 'Admin/Examenes/Index';

        // Retornamos la vista de Inertia correspondiente
        return Inertia::render($vista, [
            'examenes' => $examenes,
            'filters' => $filtrosVista,
        ]);
    }

    public function create()
    {
        $esDocente = auth()->user()->rol->nombre_rol === 'docente';

        // El docente solo ve (y puede elegir) las asignaturas que dicta.
        $asignaturas = Asignatura::query()
            ->orderBy('nombre_asignatura')
            ->when($esDocente, function ($query) {
                $query->whereHas('grupos', function ($subquery) {
                    $subquery->where('id_usuario', auth()->id());
                });
            })
            ->get(['id_asignatura', 'nombre_asignatura']);

        $ambientes = Ambiente::query()
            ->orderBy('nombre_ambiente')
            ->get(['id_ambiente', 'nombre_ambiente', 'capacidad']);

        return Inertia::render('Admin/Examenes/Create', [
            'asignaturas' => $asignaturas,
            'ambientes' => $ambientes,
        ]);
    }

    public function edit(Examen $examen)
    {
        $usuario = auth()->user();
        $esDocente = $usuario->rol->nombre_rol === 'docente';

        // El docente solo puede abrir la edición de exámenes de las asignaturas que dicta.
        if ($esDocente
            && ! Grupo::query()
                ->where('id_usuario', $usuario->id)
                ->where('id_asignatura', $examen->id_asignatura)
                ->exists()
        ) {
            abort(403);
        }

        // El docente solo ve (y puede elegir) las asignaturas que dicta.
        $asignaturas = Asignatura::query()
            ->orderBy('nombre_asignatura')
            ->when($esDocente, function ($query) {
                $query->whereHas('grupos', function ($subquery) {
                    $subquery->where('id_usuario', auth()->id());
                });
            })
            ->get(['id_asignatura', 'nombre_asignatura']);

        $ambientes = Ambiente::query()
            ->orderBy('nombre_ambiente')
            ->get(['id_ambiente', 'nombre_ambiente', 'capacidad']);

        $examen->load(['examenesAmbientes:id_examen_ambiente,id_examen,id_ambiente']);

        return Inertia::render('Admin/Examenes/Create', [
            'examen' => $examen,
            'asignaturas' => $asignaturas,
            'ambientes' => $ambientes,
        ]);
    }

    /**
     * Disponibilidad de ambientes para una ventana de fecha/hora/duración.
     * Se usa desde el formulario de registro/edición para marcar visualmente
     * qué ambientes están libres y cuáles ocupados en ese horario.
     */
    public function disponibilidad(DisponibilidadExamenRequest $request)
    {
        $datos = $request->validated();

        // Ambientes con algún examen que se solape con la ventana consultada.
        $ocupados = Examen::query()
            ->select('examen_ambiente.id_ambiente')
            ->join('examen_ambiente', 'examen_ambiente.id_examen', '=', 'examen.id_examen')
            ->when(isset($datos['excluir_examen']), function ($query) use ($datos) {
                $query->where('examen.id_examen', '!=', $datos['excluir_examen']);
            })
            ->where('examen.fecha', $datos['fecha'])
            ->whereRaw(
                "examen.hora_inicio < (CAST(? AS time) + (? * interval '1 minute'))",
                [$datos['hora_inicio'], $datos['duracion_minutos']]
            )
            ->whereRaw(
                "(examen.hora_inicio + (examen.duracion_minutos * interval '1 minute')) > CAST(? AS time)",
                [$datos['hora_inicio']]
            )
            ->pluck('examen_ambiente.id_ambiente')
            ->unique()
            ->values()
            ->all();

        $ambientes = Ambiente::query()
            ->orderBy('nombre_ambiente')
            ->get(['id_ambiente', 'nombre_ambiente', 'capacidad'])
            ->map(fn (Ambiente $ambiente) => [
                'id_ambiente' => $ambiente->id_ambiente,
                'nombre_ambiente' => $ambiente->nombre_ambiente,
                'capacidad' => $ambiente->capacidad,
                'disponible' => ! in_array($ambiente->id_ambiente, $ocupados, true),
            ])
            ->values();

        return response()->json(['ambientes' => $ambientes]);
    }

    public function store(StoreExamenRequest $request)
    {
        $datos = $request->validated();

        // Orden determinista de ambientes: reduce el riesgo de deadlock entre
        // altas concurrentes que bloquean las mismas filas en distinto orden.
        sort($datos['id_ambientes']);

        try {
            DB::transaction(function () use ($datos) {
                // Serializa altas que utilizan los mismos ambientes para evitar solapamientos concurrentes.
                Ambiente::query()
                    ->whereIn('id_ambiente', $datos['id_ambientes'])
                    ->orderBy('id_ambiente')
                    ->lockForUpdate()
                    ->get();

                if ($this->hayConflictoDeAmbiente($datos, $datos['id_ambientes'])) {
                    throw ValidationException::withMessages([
                        'id_ambientes' => 'Uno o más ambientes ya están ocupados durante ese horario.',
                    ]);
                }

                $examen = Examen::create([
                    'id_asignatura' => $datos['id_asignatura'],
                    'fecha' => $datos['fecha'],
                    'hora_inicio' => $datos['hora_inicio'],
                    'duracion_minutos' => $datos['duracion_minutos'],
                    'normas_generales' => $datos['normas_generales'] ?? null,
                ]);

                foreach ($datos['id_ambientes'] as $idAmbiente) {
                    ExamenAmbiente::create([
                        'id_examen' => $examen->id_examen,
                        'id_ambiente' => $idAmbiente,
                    ]);
                }
            });
        } catch (QueryException $e) {
            // Deadlock entre registros simultáneos que usan los mismos ambientes.
            if ($e->getCode() === '40P01') {
                return back()->withErrors([
                    'id_ambientes' => 'Se detectó otro registro simultáneo en el mismo ambiente. Inténtalo de nuevo.',
                ])->withInput();
            }

            throw $e;
        }

        return redirect()->route('examenes.index')
            ->with('success', 'Examen registrado correctamente.');
    }

    /**
     * An exam conflicts when its time interval overlaps an existing exam in at least one room.
     *
     * @param  array<string, mixed>  $datos
     */
    public function update(UpdateExamenRequest $request, Examen $examen)
    {
        $datos = $request->validated();

        try {
            DB::transaction(function () use ($examen, $datos) {
                // Horario efectivo: fusiona lo enviado con lo ya existente.
                $efectivo = [
                    'id_asignatura' => $datos['id_asignatura'] ?? $examen->id_asignatura,
                    'fecha' => $datos['fecha'] ?? $examen->fecha,
                    'hora_inicio' => $datos['hora_inicio'] ?? $examen->hora_inicio,
                    'duracion_minutos' => $datos['duracion_minutos'] ?? $examen->duracion_minutos,
                    'normas_generales' => array_key_exists('normas_generales', $datos)
                        ? $datos['normas_generales']
                        : $examen->normas_generales,
                ];

                $idAmbientes = array_key_exists('id_ambientes', $datos)
                    ? $datos['id_ambientes']
                    : $examen->examenesAmbientes()->pluck('id_ambiente')->all();

                // Orden determinista: reduce el riesgo de deadlock entre ediciones
                // concurrentes que bloquean las mismas filas en distinto orden.
                sort($idAmbientes);

                // Serializa altas que utilizan los mismos ambientes para evitar solapamientos concurrentes.
                Ambiente::query()
                    ->whereIn('id_ambiente', $idAmbientes)
                    ->orderBy('id_ambiente')
                    ->lockForUpdate()
                    ->get();

                // No se permite cambiar ambientes si ya hay ingresos registrados.
                if (array_key_exists('id_ambientes', $datos)
                    && $examen->examenesAmbientes()->whereHas('registrosIngreso')->exists()
                ) {
                    throw ValidationException::withMessages([
                        'id_ambientes' => 'No se pueden modificar los ambientes porque el examen ya tiene ingresos registrados.',
                    ]);
                }

                if ($this->hayConflictoDeAmbiente($efectivo, $idAmbientes, $examen->id_examen)) {
                    throw ValidationException::withMessages([
                        'id_ambientes' => 'Uno o más ambientes ya están ocupados durante ese horario.',
                    ]);
                }

                $examen->update($efectivo);

                if (array_key_exists('id_ambientes', $datos)) {
                    // Reemplazo total del pivot: borra las actuales y crea las nuevas.
                    $examen->examenesAmbientes()->delete();
                    foreach ($datos['id_ambientes'] as $idAmbiente) {
                        ExamenAmbiente::create([
                            'id_examen' => $examen->id_examen,
                            'id_ambiente' => $idAmbiente,
                        ]);
                    }
                }
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23503') {
                return back()->withErrors([
                    'id_ambientes' => 'No se pueden modificar los ambientes porque el examen ya tiene ingresos registrados.',
                ])->withInput();
            }

            // Deadlock entre ediciones simultáneas que usan los mismos ambientes.
            if ($e->getCode() === '40P01') {
                return back()->withErrors([
                    'id_ambientes' => 'Se detectó otra edición simultánea del mismo ambiente. Inténtalo de nuevo.',
                ])->withInput();
            }

            throw $e;
        }

        return redirect()->route('examenes.index')
            ->with('success', 'Examen actualizado correctamente.');
    }

    public function destroy(Examen $examen)
    {
        $mensaje = 'No se puede eliminar el examen porque tiene inscripciones de estudiantes o registros de ingreso asociados';

        // Se bloquea la eliminación si el examen tiene inscripciones (habilitaciones)
        // o registros de ingreso, ya que representan operaciones históricas del examen.
        if (
            $examen->habilitaciones()->exists()
            || $examen->registrosIngreso()->exists()
        ) {
            return back()->with('error', $mensaje);
        }

        try {
            // Al eliminar el examen se borran en cascada sus ambientes asociados
            // (examen_ambiente); los ambientes en sí se conservan porque son compartidos.
            DB::transaction(fn () => $examen->delete());
        } catch (QueryException $e) {
            // La clave foránea protege si se registra un ingreso durante el borrado.
            if ($e->getCode() === '23503') {
                return back()->with('error', $mensaje);
            }

            throw $e;
        }

        return redirect()->route('examenes.index')
            ->with('success', 'Examen eliminado correctamente.');
    }

    /**
     * An exam conflicts when its time interval overlaps an existing exam in at least one room.
     *
     * @param  array<string, mixed>  $datos
     * @param  array<int, int>  $idAmbientes
     */
    private function hayConflictoDeAmbiente(array $datos, array $idAmbientes, ?int $idExamenIgnorar = null): bool
    {
        return Examen::query()
            ->when($idExamenIgnorar !== null, function ($query) use ($idExamenIgnorar) {
                $query->where('id_examen', '!=', $idExamenIgnorar);
            })
            ->where('fecha', $datos['fecha'])
            ->whereHas('examenesAmbientes', function ($query) use ($idAmbientes) {
                $query->whereIn('id_ambiente', $idAmbientes);
            })
            ->whereRaw(
                "hora_inicio < (CAST(? AS time) + (? * interval '1 minute'))",
                [$datos['hora_inicio'], $datos['duracion_minutos']]
            )
            ->whereRaw(
                "(hora_inicio + (duracion_minutos * interval '1 minute')) > CAST(? AS time)",
                [$datos['hora_inicio']]
            )
            ->exists();
    }
}
