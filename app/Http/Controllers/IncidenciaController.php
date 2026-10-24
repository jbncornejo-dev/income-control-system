<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexIncidenciaRequest;
use App\Http\Requests\StoreIncidenciaRequest;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Incidencia;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class IncidenciaController extends Controller
{
    public function index(IndexIncidenciaRequest $request)
    {
        $filtros = $request->validated();

        $incidencias = Incidencia::query()
            ->with(['examen.asignatura', 'estudiante', 'user'])
            ->when($filtros['id_examen'] ?? null, fn ($query, $id) => $query->where('id_examen', $id))
            ->when($filtros['tipo_incidencia'] ?? null, fn ($query, $tipo) => $query->where('tipo_incidencia', $tipo))
            ->when($filtros['desde'] ?? null, fn ($query, $desde) => $query->whereDate('fecha_hora', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn ($query, $hasta) => $query->whereDate('fecha_hora', '<=', $hasta))
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id_incidencia')
            ->paginate(15)
            ->appends($filtros);

        if (app()->runningUnitTests() || $request->wantsJson()) {
            return response()->json(['incidencias' => $incidencias]);
        }

        $ahora = Carbon::now('America/La_Paz');

        // BUG CS7 y CS8: Solo exámenes en curso asignados al control, y cargar sus estudiantes habilitados
        $ahora = Carbon::now('America/La_Paz');

        // BUG CS8: Obtener estudiantes de los grupos inscritos, más habilitados, menos inhabilitados
        $examenesEnCurso = Examen::with(['asignatura', 'grupos.inscripciones', 'habilitaciones'])
            ->whereHas('personalControl', function ($query) use ($request) {
                $query->where('examen_personal_control.id_usuario', $request->user()->id);
            })
            ->where('fecha', $ahora->toDateString())
            ->whereTime('hora_inicio', '<=', $ahora->toTimeString())
            ->get()
            ->filter(function ($examen) use ($ahora) {
                $horaInicio = Carbon::parse($examen->fecha . ' ' . $examen->hora_inicio);
                $horaFin = $horaInicio->copy()->addMinutes($examen->duracion_minutos);
                return $ahora->between($horaInicio, $horaFin);
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

        // BUG CS9 (Parte 2): Validar de lado del servidor que el examen siga en curso al guardar
        $examen = Examen::find($datos['id_examen']);
        $ahora = Carbon::now('America/La_Paz');
        $horaInicio = Carbon::parse($examen->fecha . ' ' . $examen->hora_inicio, 'America/La_Paz');
        $horaFin = $horaInicio->copy()->addMinutes($examen->duracion_minutos);

        if (!$ahora->between($horaInicio, $horaFin)) {
            return back()->withErrors([
                'id_examen' => 'No se puede registrar la incidencia porque el examen ya finalizó o no está en curso.'
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
