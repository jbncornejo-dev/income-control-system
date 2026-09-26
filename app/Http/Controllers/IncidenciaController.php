<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexIncidenciaRequest;
use App\Http\Requests\StoreIncidenciaRequest;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Incidencia;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

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

        return Inertia::render('Incidencias/Index', [
            'incidencias' => $incidencias,
            'examenes' => Examen::with('asignatura')->orderByDesc('fecha')->get(),
            'estudiantes' => Estudiante::query()
                ->select(['id_estudiante', 'codigo_universitario', 'documento_identidad', 'nombres', 'apellidos'])
                ->orderBy('apellidos')
                ->get(),
            'tipos' => Incidencia::TIPOS,
            'filters' => $filtros,
        ]);
    }

    public function store(StoreIncidenciaRequest $request)
    {
        $datos = $request->validated();

        DB::transaction(fn () => Incidencia::create([
            'id_examen' => $datos['id_examen'],
            'id_estudiante' => $datos['id_estudiante'] ?? null,
            'id_usuario' => $request->user()->id,
            'tipo_incidencia' => $datos['tipo_incidencia'],
            'descripcion_motivo' => $datos['descripcion_motivo'],
            'fecha_hora' => now(),
        ]));

        return back()->with('success', 'Incidencia registrada correctamente.');
    }
}
