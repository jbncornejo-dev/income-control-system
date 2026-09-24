<?php

namespace App\Http\Controllers;

use App\Models\Estudiante;
use App\Models\Examen;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class MisExamenesController extends Controller
{
    /**
     * Exámenes del estudiante autenticado.
     *
     * La consulta está acotada AL estudiante: solo se devuelven los exámenes
     * que cubren alguno de los grupos en los que está inscrito (inscripcion →
     * grupo → examen_grupo) o los que tiene asociados de forma directa vía
     * habilitación. Nunca se filtra por un parámetro de la URL, así que no hay
     * forma de que un estudiante vea los exámenes de otro.
     */
    public function index(Request $request)
    {
        $estudiante = $request->user()->estudiante;

        $examenes = $estudiante
            ? $this->examenesDelEstudiante($estudiante)
            : collect();

        return Inertia::render('Estudiantes/MisExamenes', [
            'estudiante' => $estudiante ? [
                'nombres' => $estudiante->nombres,
                'apellidos' => $estudiante->apellidos,
                'codigo' => $estudiante->codigo_universitario,
            ] : null,
            'examenes' => $examenes,
            'stats' => $this->calcularStats($examenes),
        ]);
    }

    private function examenesDelEstudiante(Estudiante $estudiante): Collection
    {
        $grupoIds = $estudiante->inscripciones()->pluck('id_grupo');

        return Examen::query()
            ->with([
                'asignatura',
                'tipo',
                'periodo',
                'grupos',
                'examenesAmbientes.ambiente',
                'habilitaciones' => fn ($query) => $query->where('habilitacion.id_estudiante', $estudiante->id_estudiante),
            ])
            ->where(function ($query) use ($estudiante, $grupoIds) {
                $query
                    ->whereHas('grupos', fn ($q) => $q->whereIn('grupo.id_grupo', $grupoIds))
                    ->orWhereHas('habilitaciones', fn ($q) => $q->where('habilitacion.id_estudiante', $estudiante->id_estudiante));
            })
            ->get()
            ->map(fn (Examen $examen) => $this->serializar($examen))
            ->sort(fn (array $a, array $b) => $this->comparar($a, $b))
            ->values();
    }

    /**
     * Orden de presentación por relevancia (aplica a "Todos" y a cada pestaña):
     * 1. En curso (los que están transcurriendo ahora).
     * 2. Próximos (por fecha/hora: el más cercano primero).
     * 3. Finalizados y cerrados (por fecha/hora: el más reciente primero).
     */
    private function comparar(array $a, array $b): int
    {
        $prioridad = ['en_curso' => 0, 'programado' => 1, 'finalizado' => 2, 'suspendido' => 2, 'cancelado' => 2, 'anulado' => 2];
        $ra = $prioridad[$a['estado']] ?? 3;
        $rb = $prioridad[$b['estado']] ?? 3;

        if ($ra !== $rb) {
            return $ra <=> $rb;
        }

        $clave = fn (array $e) => $e['fecha'].' '.$e['hora_inicio'];

        return $ra <= 1
            ? strcmp($clave($a), $clave($b))
            : strcmp($clave($b), $clave($a));
    }

    private function serializar(Examen $examen): array
    {
        Carbon::setLocale('es');

        $habilitacion = $examen->habilitaciones->first();

        return [
            'id' => $examen->id_examen,
            'asignatura' => $examen->asignatura?->nombre_asignatura,
            'tipo' => $examen->tipo?->nombre,
            'periodo' => $examen->periodo_codigo,
            'fecha' => $examen->fecha,
            'fecha_formateada' => Carbon::parse($examen->fecha)->translatedFormat('l j \d\e F \d\e Y'),
            'hora_inicio' => Carbon::parse($examen->hora_inicio)->format('H:i'),
            'hora_fin' => $examen->hora_fin,
            'duracion' => $examen->duracion_minutos,
            'estado' => $examen->estado_actual,
            'ambientes' => $examen->examenesAmbientes
                ->map(fn ($examenAmbiente) => $examenAmbiente->ambiente?->nombre_ambiente)
                ->filter()
                ->values()
                ->all(),
            'grupos' => $examen->grupos->pluck('nombre_grupo')->all(),
            'normas_generales' => $examen->normas_generales,
            'habilitacion' => $habilitacion ? [
                'estado' => (bool) $habilitacion->estado_habilitado,
                'motivo' => $habilitacion->motivo_inhabilitacion,
                'normas_particulares' => $habilitacion->normas_particulares,
            ] : null,
        ];
    }

    private function calcularStats(Collection $examenes): array
    {
        return [
            'total' => $examenes->count(),
            'en_curso' => $examenes->where('estado', 'en_curso')->count(),
            'proximos' => $examenes->where('estado', 'programado')->count(),
            'finalizados' => $examenes->where('estado', 'finalizado')->count(),
            'cerrados' => $examenes->whereIn('estado', ['cancelado', 'anulado', 'suspendido'])->count(),
        ];
    }
}
