<?php

namespace App\Http\Controllers;

use App\Models\Examen;
use App\Models\Incidencia;
use App\Models\Periodo;
use App\Support\ReportExporter;
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

    public function exportarIncidencias(Request $request)
    {
        $esDocente = auth()->user()->rol->nombre_rol === 'docente';
        $idPeriodo = $request->integer('id_periodo');
        $query = Incidencia::with(['examen.asignatura', 'estudiante', 'user'])
            ->whereHas('examen', function ($q) use ($idPeriodo, $esDocente) {
                $q->where('id_periodo', $idPeriodo)->when($esDocente, fn ($sub) => $sub->whereHas(
                    'grupos', fn ($groups) => $groups->where('grupo.id_usuario', auth()->id())
                ));
            })->latest('id_incidencia');
        $rows = $query->get()->map(fn ($item) => [
            $item->examen?->asignatura?->nombre_asignatura ?? 'N/D',
            $item->tipo_incidencia,
            $item->estudiante ? trim($item->estudiante->nombres . ' ' . $item->estudiante->apellidos) : 'Sin estudiante',
            $item->user?->name ?? 'Sistema',
            $item->descripcion_motivo,
            $item->fecha_hora,
        ])->all();
        $headers = ['Asignatura', 'Tipo', 'Estudiante', 'Reportado por', 'Detalle', 'Fecha'];
        if ($request->query('formato') === 'pdf') {
            return ReportExporter::downloadPdf('incidencias-periodo.pdf', 'Incidencias del periodo', [], [
                ['heading' => 'Incidencias', 'headers' => $headers, 'rows' => $rows],
            ]);
        }
        return ReportExporter::downloadExcel('incidencias-periodo.xls', 'Incidencias del periodo', [
            ['name' => 'Incidencias', 'headers' => $headers, 'rows' => $rows],
        ]);
    }
}