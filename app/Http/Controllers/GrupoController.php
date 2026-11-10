<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexGrupoRequest;
use App\Http\Requests\StoreGrupoRequest;
use App\Http\Requests\UpdateGrupoRequest;
use App\Models\Asignatura;
use App\Models\Grupo;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class GrupoController extends Controller
{
    public function index(IndexGrupoRequest $request)
    {
        $filtros = $request->validated();

        $grupos = Grupo::query()
            ->with(['asignatura', 'usuario'])
            ->withCount('inscripciones')
            ->when($filtros['id_asignatura'] ?? null, fn ($query, $id) => $query->where('id_asignatura', $id))
            ->when($filtros['gestion'] ?? null, fn ($query, $gestion) => $query->where('gestion', $gestion))
            ->orderByDesc('gestion')
            ->orderBy('id_grupo')
            ->paginate(15)
            ->appends($filtros);

        if (app()->runningUnitTests() || $request->wantsJson()) {
            return response()->json(['grupos' => $grupos]);
        }

        return Inertia::render('Admin/Grupos/Index', [
            'grupos' => $grupos,
            'asignaturas' => Asignatura::orderBy('nombre_asignatura')->get(['id_asignatura', 'nombre_asignatura']),
            'docentes' => User::query()
                ->whereHas('rol', fn ($q) => $q->where('nombre_rol', 'docente'))
                ->orderBy('name')
                ->get(['id', 'name', 'username']),
            'gestiones' => Grupo::query()->distinct()->orderByDesc('gestion')->pluck('gestion'),
            'filters' => $filtros,
        ]);
    }

    public function store(StoreGrupoRequest $request)
    {
        try {
            DB::transaction(fn () => Grupo::create($request->validated()));
        } catch (QueryException $e) {
            if ($e->getCode() === '23505') {
                return back()->withErrors([
                    'nombre_grupo' => 'Ya existe un grupo con ese nombre para esa asignatura en esa gestion',
                ])->withInput();
            }

            throw $e;
        }

        return back()->with('success', 'Grupo registrado correctamente.');
    }

    public function update(UpdateGrupoRequest $request, Grupo $grupo)
    {
        DB::transaction(fn () => $grupo->update(['id_usuario' => $request->validated()['id_usuario']]));

        return back()->with('success', 'Docente reasignado correctamente.');
    }

    public function destroy(Grupo $grupo)
    {
        if ($grupo->inscripciones()->exists()) {
            return back()->with('error', 'No se puede eliminar el grupo porque tiene estudiantes inscritos');
        }

        try {
            DB::transaction(fn () => $grupo->delete());
        } catch (QueryException $e) {
            if ($e->getCode() === '23503') {
                return back()->with('error', 'No se puede eliminar el grupo porque tiene estudiantes inscritos');
            }

            throw $e;
        }

        return back()->with('success', 'Grupo eliminado correctamente.');
    }
}
