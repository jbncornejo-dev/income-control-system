<?php

namespace App\Http\Controllers;

use App\Http\Requests\CambiarEstadoExamenRequest;
use App\Http\Requests\DisponibilidadExamenRequest;
use App\Http\Requests\IndexExamenRequest;
use App\Http\Requests\StoreExamenRequest;
use App\Http\Requests\UpdateExamenRequest;
use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\AuditoriaLog;
use App\Models\Examen;
use App\Models\ExamenAmbiente;
use App\Models\Grupo;
use App\Models\Periodo;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ExamenController extends Controller
{
    public function index(IndexExamenRequest $request)
    {
        $filtros = $request->validated();
        $esDocente = auth()->user()->rol->nombre_rol === 'docente';

        $query = Examen::query();

        if ($esDocente) {
            // El docente solo ve los exámenes de las asignaturas que dicta (sus grupos).
            $query->whereHas('asignatura.grupos', function ($subquery) {
                $subquery->where('grupo.id_usuario', auth()->id());
            });
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

        if (isset($filtros['id_periodo'])) {
            $query->where('id_periodo', $filtros['id_periodo']);
        }

        if (isset($filtros['hora_inicio'])) {
            $query->where('hora_inicio', $filtros['hora_inicio']);
        }

        // Conteos por estado para los chips del listado. Se calculan sobre los
        // mismos filtros de búsqueda pero SIN el filtro de estado: así cada chip
        // muestra cuántos exámenes quedarían al seleccionarlo.
        $conteos = $this->conteosPorEstado($query);

        if (isset($filtros['estado'])) {
            $this->aplicarFiltroEstado($query, $filtros['estado']);
        }

        // Contexto cargado según el rol y columnas expuestas en el listado.
        $query
            ->select(['id_examen', 'id_asignatura', 'id_periodo', 'fecha', 'hora_inicio', 'duracion_minutos', 'normas_generales', 'estado'])
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->orderBy('id_examen');

        // Carga común a ambos roles.
        $query->with([
            'asignatura' => fn ($subquery) => $subquery->select(['id_asignatura', 'nombre_asignatura']),
            'periodo' => fn ($subquery) => $subquery->select(['id_periodo', 'gestion', 'tipo', 'numero']),
            'examenesAmbientes.ambiente' => fn ($subquery) => $subquery->select(['id_ambiente', 'nombre_ambiente']),
        ]);

        if ($esDocente) {
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

        $examenes = $query
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
                $examen->setAttribute('examenes_ambientes', $examen->examenesAmbientes);
                // El detalle de grupos ya se expone en "grupos"; no se repite anidado.
                $examen->asignatura?->makeHidden('grupos');

                return $examen;
            });

        $filtrosVista = [
            'asignatura' => $filtros['asignatura'] ?? null,
            'id_periodo' => $filtros['id_periodo'] ?? null,
            'fecha' => $filtros['fecha'] ?? null,
            'hora_inicio' => $filtros['hora_inicio'] ?? null,
            'estado' => $filtros['estado'] ?? null,
        ];

        // Periodos disponibles para el filtro del listado.
        $periodos = $this->periodosDisponibles();

        if (app()->runningUnitTests() || $request->wantsJson()) {
            return response()->json([
                'examenes' => $examenes,
                'filtros' => $filtrosVista,
                'periodos' => $periodos,
                'conteos' => $conteos,
            ]);
        }

        // Retornamos la vista unificada de Inertia para ambos roles
        return Inertia::render('Admin/Examenes/Index', [
            'examenes' => $examenes,
            'filters' => $filtrosVista,
            'periodos' => $periodos,
            'conteos' => $conteos,
            // Agregamos esta línea para enviar la confirmación a Vue
            'esAdmin' => auth()->user()->rol->nombre_rol === 'administrador',
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

        $periodos = $this->periodosDisponibles();

        return Inertia::render('Admin/Examenes/Create', [
            'asignaturas' => $asignaturas,
            'ambientes' => $ambientes,
            'periodos' => $periodos,
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

        // Los exámenes cancelados, anulados o finalizados no pueden editarse. "Finalizado"
        // se determina por el horario (estado_horario), no por el estado de
        // gestión: un examen suspendido cuya ventana ya terminó también queda
        // bloqueado.
        if (in_array($examen->estado, ['cancelado', 'anulado'], true) || $examen->estado_horario === 'finalizado') {
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

        $periodos = $this->periodosDisponibles();

        $examen->load(['examenesAmbientes:id_examen_ambiente,id_examen,id_ambiente']);

        return Inertia::render('Admin/Examenes/Create', [
            'examen' => $examen,
            'asignaturas' => $asignaturas,
            'ambientes' => $ambientes,
            'periodos' => $periodos,
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
                    'id_periodo' => $datos['id_periodo'],
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
        // Estado del ciclo según el horario, independiente de la decisión manual.
        // Si el examen está suspendido, su ventana sigue corriendo bajo el horario.
        $horario = $examen->estado_horario;

        // Los exámenes cancelados o anulados no pueden editarse.
        if (in_array($examen->estado, ['cancelado', 'anulado'], true) || $horario === 'finalizado') {
            throw ValidationException::withMessages([
                'estado' => 'No se puede editar un examen ya cancelado, anulado o finalizado.',
            ]);
        }

        // Mientras la ventana de ingreso está activa (en curso, incluido
        // suspendido) solo se pueden actualizar las normas generales: fecha,
        // hora, duración, ambientes, asignatura y periodo definen la ventana y
        // el ingreso, por lo que quedan congelados.
        $camposEstructurales = ['id_asignatura', 'id_periodo', 'fecha', 'hora_inicio', 'duracion_minutos', 'id_ambientes'];

        if ($horario === 'en_curso' && $request->anyFilled(...$camposEstructurales)) {
            throw ValidationException::withMessages([
                'estado' => 'Un examen en curso solo permite editar las normas generales.',
            ]);
        }

        $datos = $request->validated();

        try {
            DB::transaction(function () use ($examen, $datos) {
                // Horario efectivo: fusiona lo enviado con lo ya existente.
                $efectivo = [
                    'id_asignatura' => $datos['id_asignatura'] ?? $examen->id_asignatura,
                    'id_periodo' => $datos['id_periodo'] ?? $examen->id_periodo,
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

    /**
     * Cambia el estado manual del examen (cancelar, anular, suspender o reanudar).
     * Las transiciones automáticas (programado -> en_curso -> finalizado)
     * no se guardan: se derivan del horario en `estado_actual`.
     *
     * Semántica de gestión:
     * - 'cancelar' (-> 'cancelado'): solo un examen programado que aún no empieza.
     * - 'anular' (-> 'anulado'): solo un examen en curso (o suspendido cuya
     *   ventana siga activa), para invalidar lo ocurrido.
     * - 'suspender' (-> 'suspendido'): solo un examen en curso.
     * - 'reanudar' (-> null): solo un examen suspendido.
     */
    public function cambiarEstado(CambiarEstadoExamenRequest $request, Examen $examen)
    {
        $accion = $request->validated()['accion'];

        $nuevoEstado = match ($accion) {
            'cancelar' => 'cancelado',
            'anular' => 'anulado',
            'suspender' => 'suspendido',
            'reanudar' => null,
        };

        // Estado automático según el horario (sin considerar la decisión manual).
        $inicio = Carbon::parse($examen->fecha.' '.$examen->hora_inicio);
        $fin = $inicio->copy()->addMinutes((int) $examen->duracion_minutos);
        $automatico = now()->lt($inicio)
            ? 'programado'
            : (now()->lt($fin) ? 'en_curso' : 'finalizado');

        // Los estados terminales (cancelado/anulado) son definitivos: congelan
        // el examen y no admiten cambios.
        if ($examen->estado === 'cancelado' || $examen->estado === 'anulado') {
            $motivo = $examen->estado === 'cancelado'
                ? 'El examen ya está cancelado y es definitivo; no se puede modificar.'
                : 'El examen ya está anulado y es definitivo; no se puede modificar.';

            throw ValidationException::withMessages(['estado' => $motivo]);
        }

        // Cancelar solo tiene sentido antes de que el examen empiece: un examen
        // que ya está en marcha se anula, no se cancela.
        if ($accion === 'cancelar' && $automatico !== 'programado') {
            throw ValidationException::withMessages([
                'estado' => 'Solo se puede cancelar un examen que aún no ha comenzado.',
            ]);
        }

        // Anular solo tiene sentido mientras el examen está en curso (o
        // suspendido con la ventana aún activa): un examen que no ha empezado
        // se cancela, no se anula.
        if ($accion === 'anular' && $automatico !== 'en_curso') {
            throw ValidationException::withMessages([
                'estado' => 'Solo se puede anular un examen que está en curso.',
            ]);
        }

        // Suspender solo tiene sentido mientras el examen está en curso:
        // un examen que aún no empieza se cancela, no se suspende.
        if ($accion === 'suspender' && $automatico !== 'en_curso') {
            throw ValidationException::withMessages([
                'estado' => 'Solo se puede suspender un examen que está en curso.',
            ]);
        }

        if ($nuevoEstado !== null && $automatico === 'finalizado') {
            throw ValidationException::withMessages([
                'estado' => 'No se puede cancelar, anular o suspender un examen que ya finalizó.',
            ]);
        }

        if ($nuevoEstado !== null && $examen->estado === $nuevoEstado) {
            $yaEnEstado = match ($accion) {
                'cancelar' => 'El examen ya está cancelado.',
                'anular' => 'El examen ya está anulado.',
                default => 'El examen ya está suspendido.',
            };

            throw ValidationException::withMessages(['estado' => $yaEnEstado]);
        }

        if ($nuevoEstado === null && $examen->estado !== 'suspendido') {
            throw ValidationException::withMessages([
                'estado' => 'Solo se puede reanudar un examen suspendido.',
            ]);
        }

        DB::transaction(function () use ($request, $examen, $nuevoEstado, $accion) {
            $examen->update(['estado' => $nuevoEstado]);

            AuditoriaLog::create([
                'id_usuario' => $request->user()->id,
                'tabla_afectada' => 'examen',
                'id_registro_afectado' => $examen->id_examen,
                'accion' => strtoupper($accion),
            ]);
        });

        $mensajes = [
            'cancelar' => 'Examen cancelado correctamente.',
            'anular' => 'Examen anulado correctamente.',
            'suspender' => 'Examen suspendido correctamente.',
            'reanudar' => 'Examen reanudado correctamente.',
        ];

        return back()->with('success', $mensajes[$accion]);
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

    /**
     * Conteos por estado para los chips del listado. Se calculan sobre la
     * misma query de búsqueda (asignatura, periodo, fecha, hora, alcance de
     * rol) pero sin el filtro de estado, así cada chip refleja cuántos
     * quedarían al seleccionarlo.
     *
     * @return array<string, int>
     */
    private function conteosPorEstado(Builder $query): array
    {
        // Se embebe el literal directamente (sin placeholders) porque Postgres
        // exige que la expresión del SELECT y la del GROUP BY sean idénticas
        // textualmente; los bindings de selectRaw/groupByRaw se inlinean de
        // forma distinta y rompen esa comparación.
        $ahora = now()->format('Y-m-d H:i:s');
        $ahoraLiteral = "'{$ahora}'::timestamp";
        $inicio = "(fecha::text || ' ' || hora_inicio::text)::timestamp";

        $case = "CASE
                WHEN estado = 'cancelado' THEN 'cancelado'
                WHEN estado = 'anulado' THEN 'anulado'
                WHEN estado = 'suspendido' THEN 'suspendido'
                WHEN {$inicio} > {$ahoraLiteral} THEN 'programado'
                WHEN {$inicio} + (duracion_minutos * interval '1 minute') > {$ahoraLiteral} THEN 'en_curso'
                ELSE 'finalizado'
            END";

        $filas = (clone $query)
            ->selectRaw("{$case} AS bucket, COUNT(*) AS total")
            ->groupByRaw($case)
            ->get();

        $mapa = [];
        foreach ($filas as $fila) {
            $mapa[$fila->bucket] = (int) $fila->total;
        }

        return array_replace([
            'programado' => 0,
            'en_curso' => 0,
            'finalizado' => 0,
            'cancelado' => 0,
            'anulado' => 0,
            'suspendido' => 0,
        ], $mapa);
    }

    /**
     * Aplica el filtro de estado al listado: los buckets de gestión
     * (cancelado/anulado/suspendido) se resuelven por la columna `estado`, los
     * buckets del ciclo (programado/en_curso/finalizado) requieren
     * comparar el horario con la hora actual.
     */
    private function aplicarFiltroEstado(Builder $query, string $estado): void
    {
        $ahora = now()->format('Y-m-d H:i:s');
        $inicio = "(fecha::text || ' ' || hora_inicio::text)::timestamp";
        $fin = "{$inicio} + (duracion_minutos * interval '1 minute')";

        match ($estado) {
            'cancelado' => $query->where('estado', 'cancelado'),
            'anulado' => $query->where('estado', 'anulado'),
            'suspendido' => $query->where('estado', 'suspendido'),
            'programado' => $query->whereNull('estado')
                ->whereRaw("{$inicio} > CAST(? AS timestamp)", [$ahora]),
            'en_curso' => $query->whereNull('estado')
                ->whereRaw("{$inicio} <= CAST(? AS timestamp)", [$ahora])
                ->whereRaw("{$fin} > CAST(? AS timestamp)", [$ahora]),
            'finalizado' => $query->whereNull('estado')
                ->whereRaw("{$fin} <= CAST(? AS timestamp)", [$ahora]),
            default => null,
        };
    }

    /**
     * Periodos disponibles para elegir o filtrar, de más reciente a más antiguo.
     *
     * @return Collection<int, Periodo>
     */
    private function periodosDisponibles()
    {
        return Periodo::query()
            ->orderByDesc('gestion')
            ->orderByDesc('numero')
            ->get(['id_periodo', 'gestion', 'tipo', 'numero', 'fecha_inicio', 'fecha_fin']);
    }
}
