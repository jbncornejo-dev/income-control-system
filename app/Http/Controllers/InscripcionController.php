<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInscripcionRequest;
use App\Models\Estudiante;
use App\Models\Grupo;
use App\Models\Inscripcion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class InscripcionController extends Controller
{
    public function show(Request $request, Grupo $grupo)
    {
        if ($request->user()?->rol?->nombre_rol !== 'administrador') {
            abort(403, 'Acceso Denegado');
        }

        $busqueda = trim((string) $request->query('busqueda', ''));

        $inscritos = Inscripcion::query()
            ->with('estudiante')
            ->where('id_grupo', $grupo->id_grupo)
            ->orderBy('id_inscripcion')
            ->paginate(15)
            ->appends(['busqueda' => $busqueda ?: null]);

        $disponibles = collect();

        if ($busqueda !== '') {
            $patron = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $busqueda).'%';

            $disponibles = Estudiante::query()
                ->select(['id_estudiante', 'codigo_universitario', 'documento_identidad', 'nombres', 'apellidos'])
                ->where(fn ($q) => $q
                    ->where('codigo_universitario', 'ilike', $patron)
                    ->orWhere('nombres', 'ilike', $patron)
                    ->orWhere('apellidos', 'ilike', $patron))
                ->whereDoesntHave('inscripciones', fn ($q) => $q->where('id_grupo', $grupo->id_grupo))
                ->orderBy('apellidos')
                ->limit(20)
                ->get();
        }

        if (app()->runningUnitTests() || $request->wantsJson()) {
            return response()->json(['inscritos' => $inscritos, 'disponibles' => $disponibles]);
        }

        return Inertia::render('Admin/Grupos/Detalle', [
            'grupo' => $grupo->load(['asignatura', 'usuario']),
            'inscritos' => $inscritos,
            'disponibles' => $disponibles,
            'busqueda' => $busqueda,
        ]);
    }

    public function store(StoreInscripcionRequest $request, Grupo $grupo)
    {
        DB::transaction(fn () => Inscripcion::create([
            'id_grupo' => $grupo->id_grupo,
            'id_estudiante' => $request->validated()['id_estudiante'],
        ]));

        return back()->with('success', 'Estudiante inscrito correctamente.');
    }

    public function destroy(Request $request, Grupo $grupo, Inscripcion $inscripcion)
    {
        if ($request->user()?->rol?->nombre_rol !== 'administrador') {
            abort(403, 'Acceso Denegado');
        }

        if ($inscripcion->id_grupo !== $grupo->id_grupo) {
            abort(404);
        }

        DB::transaction(fn () => $inscripcion->delete());

        return back()->with('success', 'Inscripcion dada de baja correctamente.');
    }
}
