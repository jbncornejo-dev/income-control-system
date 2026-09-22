<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexTipoExamenRequest;
use App\Http\Requests\StoreTipoExamenRequest;
use App\Http\Requests\UpdateTipoExamenRequest;
use App\Models\Periodo;
use App\Models\PeriodoTipoExamen;
use App\Models\TipoExamen;
use Illuminate\Database\QueryException;
use Inertia\Inertia;

class TipoExamenController extends Controller
{
    /**
     * Lista el catálogo de tipos de examen y, junto a él, todos los periodos con
     * su plan de evaluación (periodo_tipo_examen) para que el administrador
     * defina qué tipos rigen en cada gestión y en qué orden.
     */
    public function index(IndexTipoExamenRequest $request)
    {
        $filtros = $request->validated();

        $query = TipoExamen::query();

        if (isset($filtros['nombre'])) {
            $query->where('nombre', 'ilike', '%'.$filtros['nombre'].'%');
        }

        if (isset($filtros['codigo'])) {
            $query->where('codigo', 'ilike', '%'.$filtros['codigo'].'%');
        }

        $tipos = $query
            ->orderBy('nombre')
            ->paginate(15)
            ->appends($filtros);

        $periodos = Periodo::query()
            ->with(['tiposExamen' => fn ($subquery) => $subquery->orderBy('periodo_tipo_examen.orden')])
            ->orderByDesc('gestion')
            ->orderBy('numero')
            ->get(['id_periodo', 'gestion', 'tipo', 'numero']);

        if (app()->runningUnitTests() || $request->wantsJson()) {
            return response()->json([
                'tipos' => $tipos,
                'periodos' => $periodos,
                'filtros' => [
                    'nombre' => $filtros['nombre'] ?? null,
                    'codigo' => $filtros['codigo'] ?? null,
                ],
            ]);
        }

        return Inertia::render('TiposExamen/Index', [
            'tipos' => $tipos,
            'periodos' => $periodos,
            'filtros' => [
                'nombre' => $filtros['nombre'] ?? null,
                'codigo' => $filtros['codigo'] ?? null,
            ],
        ]);
    }

    public function store(StoreTipoExamenRequest $request)
    {
        try {
            TipoExamen::create($request->validated());
        } catch (QueryException $e) {
            // 23505: unique_violation de nombre o código.
            if ($e->getCode() === '23505') {
                return back()
                    ->withErrors(['nombre' => 'Ya existe un tipo de examen con ese nombre o código.'])
                    ->withInput();
            }

            throw $e;
        }

        return back()->with('success', 'Tipo de examen registrado correctamente.');
    }

    public function update(UpdateTipoExamenRequest $request, TipoExamen $tipoExamen)
    {
        try {
            $tipoExamen->update($request->validated());
        } catch (QueryException $e) {
            // 23505: unique_violation de nombre o código.
            if ($e->getCode() === '23505') {
                return back()
                    ->withErrors(['nombre' => 'Ya existe un tipo de examen con ese nombre o código.'])
                    ->withInput();
            }

            throw $e;
        }

        return back()->with('success', 'Tipo de examen actualizado correctamente.');
    }

    public function destroy(TipoExamen $tipoExamen)
    {
        $mensaje = 'No se puede eliminar el tipo de examen porque está en uso por exámenes o planes de periodo.';

        // No se borra un tipo que clasifica exámenes existentes ni un tipo que
        // integra el plan de evaluación de algún periodo.
        if ($tipoExamen->examenes()->exists()
            || PeriodoTipoExamen::where('id_tipo_examen', $tipoExamen->id_tipo_examen)->exists()
        ) {
            return back()->with('error', $mensaje);
        }

        $tipoExamen->delete();

        return back()->with('success', 'Tipo de examen eliminado correctamente.');
    }
}
