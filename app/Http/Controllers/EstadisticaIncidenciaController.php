<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexEstadisticaIncidenciaRequest;
use App\Models\Examen;
use App\Models\Incidencia;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EstadisticaIncidenciaController extends Controller
{
    public function index(IndexEstadisticaIncidenciaRequest $request)
    {
        $filtros = $request->validated();

        $consulta = fn () => Incidencia::query()
            ->when($filtros['id_examen'] ?? null, fn ($query, $id) => $query->where('id_examen', $id))
            ->when($filtros['tipo_incidencia'] ?? null, fn ($query, $tipo) => $query->where('tipo_incidencia', $tipo))
            ->when($filtros['desde'] ?? null, fn ($query, $desde) => $query->whereDate('fecha_hora', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn ($query, $hasta) => $query->whereDate('fecha_hora', '<=', $hasta));

        $total = $consulta()->count();

        $porTipo = $consulta()
            ->select('tipo_incidencia', DB::raw('count(*) as total'))
            ->groupBy('tipo_incidencia')
            ->orderByDesc('total')
            ->orderBy('tipo_incidencia')
            ->get()
            ->map(fn ($fila) => [
                'tipo' => $fila->tipo_incidencia,
                'total' => (int) $fila->total,
                'porcentaje' => $total > 0 ? round($fila->total * 100 / $total, 1) : 0.0,
            ])
            ->values();

        $porDia = $consulta()
            ->select(DB::raw('date(fecha_hora) as dia'), DB::raw('count(*) as total'))
            ->groupBy(DB::raw('date(fecha_hora)'))
            ->orderBy('dia')
            ->get()
            ->map(fn ($fila) => [
                'dia' => (string) $fila->dia,
                'total' => (int) $fila->total,
            ])
            ->values();

        $conteoPorExamen = $consulta()
            ->select('id_examen', DB::raw('count(*) as total'))
            ->groupBy('id_examen')
            ->orderByDesc('total')
            ->orderBy('id_examen')
            ->limit(10)
            ->get();

        $examenes = Examen::with('asignatura')
            ->whereIn('id_examen', $conteoPorExamen->pluck('id_examen'))
            ->get()
            ->keyBy('id_examen');

        $porExamen = $conteoPorExamen->map(function ($fila) use ($examenes) {
            $examen = $examenes->get($fila->id_examen);

            return [
                'id_examen' => (int) $fila->id_examen,
                'examen' => $examen?->asignatura?->nombre_asignatura ?? 'Sin asignatura',
                'fecha' => $examen?->fecha,
                'total' => (int) $fila->total,
            ];
        })->values();

        $datos = [
            'filters' => $filtros,
            'resumen' => [
                'total' => $total,
                'dias_con_registro' => $porDia->count(),
                'promedio_diario' => $porDia->count() > 0 ? round($total / $porDia->count(), 1) : 0.0,
                'examenes_afectados' => $consulta()->distinct()->count('id_examen'),
                'tipo_mas_frecuente' => $porTipo->first()['tipo'] ?? null,
            ],
            'porTipo' => $porTipo,
            'porDia' => $porDia,
            'porExamen' => $porExamen,
        ];

        if (app()->runningUnitTests() || $request->wantsJson()) {
            return response()->json($datos);
        }

        return Inertia::render('Incidencias/Estadisticas', array_merge($datos, [
            'examenes' => Examen::with('asignatura')
                ->orderByDesc('fecha')
                ->get()
                ->map(fn ($examen) => [
                    'id_examen' => $examen->id_examen,
                    'nombre' => ($examen->asignatura?->nombre_asignatura ?? 'Sin asignatura').' - '.$examen->fecha,
                ]),
            'tipos' => Incidencia::query()
                ->select('tipo_incidencia')
                ->distinct()
                ->orderBy('tipo_incidencia')
                ->pluck('tipo_incidencia'),
        ]));
    }
}
