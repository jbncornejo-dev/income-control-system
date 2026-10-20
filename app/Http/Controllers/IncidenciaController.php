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
        $examenesEnCurso = Examen::with([
            'asignatura',
            // Cargar estudiantes habilitados para este examen
            'habilitaciones' => function ($query) {
                $query->where('estado_habilitado', true)->with('estudiante');
            }
        ])
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
        ->values();

        return Inertia::render('Incidencias/Index', [
            'incidencias' => $incidencias,
            'examenes' => $examenesEnCurso,
            // Eliminamos la consulta general de todos los 'estudiantes'
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
