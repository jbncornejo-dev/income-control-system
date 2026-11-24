<?php

namespace App\Http\Controllers;

use App\Models\Asignatura;
use App\Models\Examen;
use App\Models\Incidencia;
use App\Models\Periodo;
use App\Models\Rol;
use App\Support\ReadSnapshot;
use App\Support\ReportExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportesController extends Controller
{
    public function index(Request $request)
    {
        $rol = $request->user()->rol;
        abort_unless($rol?->nombre_rol === 'administrador' || in_array('/reportes', $rol?->recursos() ?? [], true), 403);

        $reportes = array_filter(
            Rol::PERMISOS_REPORTES,
            fn ($permiso) => $rol->nombre_rol === 'administrador' || $rol->puedeExportar($permiso),
            ARRAY_FILTER_USE_KEY,
        );

        return Inertia::render('Reportes/Index', [
            'reportes' => (object) $reportes,
            'asignaturas' => fn () => $reportes ? Asignatura::orderBy('nombre_asignatura')
                ->when($rol->esPersonalizado() && $rol->asignaturas_reportes !== null,
                    fn ($query) => $query->whereKey($rol->asignaturas_reportes))
                ->get(['id_asignatura', 'nombre_asignatura']) : [],
            'periodos' => fn () => $reportes ? Periodo::orderByDesc('gestion')->orderByDesc('numero')->get() : [],
        ]);
    }

    public function examenes(Request $request)
    {
        $filtros = $this->filtros($request);
        $query = Examen::with('asignatura')->withCount('incidencias')->latest('fecha')->latest('hora_inicio');
        $this->filtrarExamenes($query, $filtros)
            ->where(fn ($query) => $query->whereNull('estado')->orWhereNotIn('estado', ['cancelado', 'anulado', 'suspendido']))
            ->whereRaw("(fecha + hora_inicio + duracion_minutos * interval '1 minute') <= ?::timestamp", [now()->toDateTimeString()]);

        return ReadSnapshot::run(fn () => $this->exportar(
            $filtros['formato'],
            'examenes',
            'Exámenes realizados',
            ['Asignatura', 'Fecha', 'Hora de inicio', 'Duración (min)', 'Incidencias'],
            $query->lazy(500)->map(fn (Examen $examen) => [
                $examen->asignatura->nombre_asignatura,
                $examen->fecha,
                $examen->hora_inicio,
                $examen->duracion_minutos,
                $examen->incidencias_count,
            ]),
        ));
    }

    public function incidencias(Request $request)
    {
        $filtros = $this->filtros($request);
        $query = Incidencia::with('examen.asignatura', 'estudiante')
            ->whereHas('examen', fn ($query) => $this->filtrarExamenes($query, $filtros))
            ->latest('fecha_hora')->latest('id_incidencia');

        return ReadSnapshot::run(fn () => $this->exportar(
            $filtros['formato'],
            'incidencias',
            'Incidencias por examen',
            ['Examen', 'Asignatura', 'Fecha del examen', 'Estudiante', 'Tipo', 'Detalle', 'Hora de la incidencia'],
            $query->lazy(500)->map(fn (Incidencia $incidencia) => [
                $incidencia->id_examen,
                $incidencia->examen->asignatura->nombre_asignatura,
                $incidencia->examen->fecha,
                $incidencia->estudiante
                    ? $incidencia->estudiante->nombres.' '.$incidencia->estudiante->apellidos
                    : 'Sin estudiante',
                $incidencia->tipo_incidencia,
                $incidencia->descripcion_motivo,
                $incidencia->fecha_hora,
            ]),
        ));
    }

    private function filtros(Request $request): array
    {
        $rol = $request->user()->rol;
        abort_unless($rol?->nombre_rol === 'administrador' || $rol?->puedeExportar($request->route()->getName()), 403);

        $filtros = $request->validate([
            'formato' => ['required', 'in:pdf,excel'],
            'id_periodo' => ['required', 'integer', 'exists:periodo,id_periodo'],
            'id_asignatura' => ['nullable', 'integer', 'min:1'],
        ]);

        $asignaturas = $rol->esPersonalizado() ? $rol->asignaturas_reportes : null;
        if ($asignaturas !== null && isset($filtros['id_asignatura'])) {
            abort_unless(in_array((int) $filtros['id_asignatura'], $asignaturas, true), 403);
        }
        $filtros['asignaturas_autorizadas'] = $asignaturas;

        return $filtros;
    }

    private function filtrarExamenes(Builder $query, array $filtros): Builder
    {
        return $query->where('id_periodo', $filtros['id_periodo'])
            ->when($filtros['asignaturas_autorizadas'] !== null,
                fn ($query) => $query->whereIn('id_asignatura', $filtros['asignaturas_autorizadas']))
            ->when(! empty($filtros['id_asignatura']), fn ($query) => $query->where('id_asignatura', $filtros['id_asignatura']));
    }

    private function exportar(string $formato, string $archivo, string $titulo, array $headers, iterable $rows)
    {
        return $formato === 'pdf'
            ? ReportExporter::downloadPdf($archivo.'.pdf', $titulo, [], [['heading' => $titulo, 'headers' => $headers, 'rows' => $rows]])
            : ReportExporter::downloadExcel($archivo.'.xls', $titulo, [['name' => $titulo, 'headers' => $headers, 'rows' => $rows]]);
    }
}
