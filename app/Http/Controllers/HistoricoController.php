<?php

namespace App\Http\Controllers;

use App\Models\Examen;
use App\Models\Incidencia;
use App\Models\Periodo;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HistoricoController extends Controller
{
    public function index(Request $request)
    {
        $esDocente = auth()->user()->rol->nombre_rol === 'docente';

        // 1. Catálogo de periodos para el selector (más recientes primero)
        $periodos = Periodo::query()
            ->orderByDesc('gestion')
            ->orderByDesc('numero')
            ->get(['id_periodo', 'gestion', 'tipo', 'numero']);

        // Periodo seleccionado o por defecto el más reciente
        $idPeriodo = $request->integer('id_periodo') ?: $periodos->first()?->id_periodo;

        // 2. Query de Exámenes del periodo (filtrado por docente si corresponde)
        $examenesQuery = Examen::query()
            ->where('id_periodo', $idPeriodo)
            ->when($esDocente, function ($query) {
                $query->whereHas('grupos', function ($subquery) {
                    $subquery->where('grupo.id_usuario', auth()->id());
                });
            })
            ->with([
                'asignatura:id_asignatura,nombre_asignatura',
                'tipo:id_tipo_examen,nombre',
                'grupos:grupo.id_grupo,grupo.nombre_grupo',
            ])
            ->withCount([
                'habilitaciones',
                'registrosIngreso',
                'incidencias',
            ])
            ->orderByDesc('fecha')
            ->orderByDesc('hora_inicio');

        // 3. Query de Incidencias del periodo
        $incidenciasQuery = Incidencia::query()
            ->whereHas('examen', function ($query) use ($idPeriodo, $esDocente) {
                $query->where('id_periodo', $idPeriodo)
                    ->when($esDocente, function ($subquery) {
                        $subquery->whereHas('grupos', function ($gQuery) {
                            $gQuery->where('grupo.id_usuario', auth()->id());
                        });
                    });
            })
            ->with([
                'examen.asignatura:id_asignatura,nombre_asignatura',
                'estudiante:id_estudiante,nombres,apellidos,codigo_universitario',
                'user:id,name',
            ])
            ->orderByDesc('id_incidencia');

        // Paginaciones con claves distintas para no colisionar en la misma vista
        $examenes = $examenesQuery->paginate(10, ['*'], 'examenes_page')->withQueryString();
        $incidencias = $incidenciasQuery->paginate(10, ['*'], 'incidencias_page')->withQueryString();

        return Inertia::render('Historico/Index', [
            'periodos' => $periodos,
            'idPeriodoSeleccionado' => $idPeriodo,
            'examenes' => $examenes,
            'incidencias' => $incidencias,
            'esDocente' => $esDocente,
        ]);
    }
}