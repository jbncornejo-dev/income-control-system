<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexIncidenciaRequest;
use App\Http\Requests\StoreIncidenciaRequest;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Incidencia;
use App\Services\ReporteExportService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class IncidenciaController extends Controller
{
    /**
     * Construir la consulta de incidencias con los filtros aplicados.
     */
    private function queryIncidencias(array $filtros)
    {
        return Incidencia::query()
            ->with(['examen.asignatura', 'estudiante', 'user'])
            ->when($filtros['id_examen'] ?? null, fn ($query, $id) => $query->where('id_examen', $id))
            ->when($filtros['tipo_incidencia'] ?? null, fn ($query, $tipo) => $query->where('tipo_incidencia', $tipo))
            ->when($filtros['desde'] ?? null, fn ($query, $desde) => $query->whereDate('fecha_hora', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn ($query, $hasta) => $query->whereDate('fecha_hora', '<=', $hasta))
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id_incidencia');
    }

    public function index(IndexIncidenciaRequest $request)
    {
        $filtros = $request->validated();

        $incidencias = $this->queryIncidencias($filtros)
            ->paginate(15)
            ->appends($filtros);

        if (app()->runningUnitTests() || $request->wantsJson()) {
            return response()->json(['incidencias' => $incidencias]);
        }

        $ahora = Carbon::now('America/La_Paz');

        // HU-17: Solo exámenes dentro de la ventana de control (-10 min inicio a +10 min fin) asignados al control
        $examenesEnCurso = Examen::with(['asignatura', 'grupos.inscripciones', 'habilitaciones'])
            ->whereHas('personalControl', function ($query) use ($request) {
                $query->where('examen_personal_control.id_usuario', $request->user()->id);
            })
            ->where(function ($q) {
                $q->whereNull('estado')
                  ->orWhereNotIn('estado', ['cancelado', 'anulado']);
            })
            ->get()
            ->filter(function ($examen) use ($ahora) {
                return $examen->estaEnVentanaOperacionControl($ahora);
            })
            ->map(function ($examen) {
                // 1. Obtener IDs de estudiantes normales inscritos en los grupos del examen
                $idsGrupos = $examen->grupos->flatMap->inscripciones->pluck('id_estudiante')->toArray();
                
                // 2. Obtener IDs de excepciones (habilitados / inhabilitados explícitamente)
                $idsHabilitados = $examen->habilitaciones->filter(fn($h) => $h->estado_habilitado == true)->pluck('id_estudiante')->toArray();
                $idsInhabilitados = $examen->habilitaciones->filter(fn($h) => $h->estado_habilitado == false)->pluck('id_estudiante')->toArray();
                
                // 3. Unir grupos + habilitados, y luego restar los inhabilitados
                $examen->estudiantes_habilitados_ids = collect(array_merge($idsGrupos, $idsHabilitados))
                    ->unique()
                    ->diff($idsInhabilitados)
                    ->values()
                    ->toArray();

                $examen->horario_permitido = $examen->horarioPermitidoControl();
                    
                // Limpiar la carga pesada para no enviar datos innecesarios a Vue
                $examen->unsetRelation('grupos');
                $examen->unsetRelation('habilitaciones');
                
                return $examen;
            })
            ->values();

        return Inertia::render('Incidencias/Index', [
            'incidencias' => $incidencias,
            'examenes' => $examenesEnCurso,
            // Volvemos a enviar la lista de estudiantes para que Vue los cruce con los IDs
            'estudiantes' => Estudiante::query()
                ->select(['id_estudiante', 'codigo_universitario', 'documento_identidad', 'nombres', 'apellidos'])
                ->orderBy('apellidos')
                ->get(),
            'tipos' => Incidencia::TIPOS,
            'filters' => $filtros,
        ]);
    }

    /**
     * HU-26: Exportar histórico de incidencias a PDF respetando filtros activos.
     */
    public function exportarPdf(IndexIncidenciaRequest $request, ReporteExportService $exportService)
    {
        $filtros = $request->validated();
        $incidencias = $this->queryIncidencias($filtros)->get();
        $examenFiltro = !empty($filtros['id_examen']) ? Examen::with('asignatura')->find($filtros['id_examen']) : null;

        return $exportService->exportarIncidenciasPdf($incidencias, $filtros, $examenFiltro);
    }

    /**
     * HU-26: Exportar histórico de incidencias a Excel (.xlsx) respetando filtros activos.
     */
    public function exportarExcel(IndexIncidenciaRequest $request, ReporteExportService $exportService)
    {
        $filtros = $request->validated();
        $incidencias = $this->queryIncidencias($filtros)->get();
        $examenFiltro = !empty($filtros['id_examen']) ? Examen::with('asignatura')->find($filtros['id_examen']) : null;

        return $exportService->exportarIncidenciasExcel($incidencias, $filtros, $examenFiltro);
    }

    public function store(\Illuminate\Http\Request $request)
    {
        $mensajes = [
            'required' => 'Debe rellenar este campo para proceder'
        ];

        $datos = $request->validate([
            'id_examen' => 'required|exists:examen,id_examen',
            'id_estudiante' => 'nullable|exists:estudiante,id_estudiante',
            'tipo_incidencia' => 'required|in:' . implode(',', Incidencia::TIPOS),
            'descripcion_motivo' => 'required|string',
        ], $mensajes);

        // HU-17: Validar que el examen se encuentre en la ventana de control permitida
        // (-10 min del inicio a +10 min del fin)
        $examen = Examen::find($datos['id_examen']);
        $ahora = Carbon::now('America/La_Paz');

        if (! $examen || ! $examen->estaEnVentanaOperacionControl($ahora)) {
            $horario = $examen ? $examen->horarioPermitidoControl() : '';
            $msg = 'No se puede registrar la incidencia: solo está permitido entre 10 minutos antes del inicio y 10 minutos después de la finalización del examen' . ($horario ? " (Horario permitido: {$horario})" : '') . '.';

            if ($request->wantsJson()) {
                return response()->json(['error' => $msg, 'message' => $msg], 422);
            }

            return back()->withErrors([
                'id_examen' => $msg,
            ]);
        }

        DB::transaction(fn () => Incidencia::create([
            'id_examen' => $datos['id_examen'],
            'id_estudiante' => $datos['id_estudiante'] ?? null,
            'id_usuario' => $request->user()->id,
            'tipo_incidencia' => $datos['tipo_incidencia'],
            'descripcion_motivo' => $datos['descripcion_motivo'],
            'fecha_hora' => now(),
            'fecha_registro' => Carbon::now('America/La_Paz'),
        ]));

        return back()->with('success', 'Incidencia registrada correctamente.');
    }
}
