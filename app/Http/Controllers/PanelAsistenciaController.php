<?php

namespace App\Http\Controllers;

use App\Models\Examen;
use App\Models\Habilitacion;
use App\Models\RegistroIngreso;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PanelAsistenciaController extends Controller
{
    private const ROLES = ['administrador', 'docente', 'personal de control de ingreso'];

    public function index(Request $request)
    {
        if (! in_array($request->user()?->rol?->nombre_rol, self::ROLES, true)) {
            abort(403, 'Acceso Denegado');
        }

        $examenes = Examen::query()
            ->with(['asignatura', 'tipo'])
            ->where(fn ($q) => $q->whereNull('estado')->orWhereNotIn('estado', ['cancelado', 'anulado']))
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get()
            ->filter(fn ($examen) => in_array($examen->estado_horario, ['programado', 'en_curso'], true))
            ->values();

        $seleccionado = $request->query('id_examen')
            ? Examen::with(['asignatura', 'tipo', 'examenesAmbientes.ambiente'])->find($request->query('id_examen'))
            : null;

        if (! $seleccionado) {
            return $this->responder($request, [
                'examenes' => $examenes,
                'examen' => null,
                'ambientes' => [],
                'pendientes' => [],
                'resumen' => null,
            ]);
        }

        $habilitados = Habilitacion::query()
            ->with('estudiante')
            ->where('id_examen', $seleccionado->id_examen)
            ->where('estado_habilitado', true)
            ->get();

        $ingresos = RegistroIngreso::query()
            ->with('estudiante')
            ->whereHas('examenAmbiente', fn ($q) => $q->where('id_examen', $seleccionado->id_examen))
            ->get();

        $idsIngresados = $ingresos->pluck('id_estudiante')->all();

        $ambientes = $seleccionado->examenesAmbientes->map(function ($examenAmbiente) use ($ingresos, $habilitados) {
            $delAmbiente = $ingresos
                ->where('id_examen_ambiente', $examenAmbiente->id_examen_ambiente)
                ->sortBy('fecha_hora_ingreso')
                ->values()
                ->map(fn ($registro) => [
                    'id_estudiante' => $registro->id_estudiante,
                    'codigo_universitario' => $registro->estudiante?->codigo_universitario,
                    'nombres' => $registro->estudiante?->nombres,
                    'apellidos' => $registro->estudiante?->apellidos,
                    'hora_ingreso' => $registro->fecha_hora_ingreso,
                ]);

            return [
                'id_examen_ambiente' => $examenAmbiente->id_examen_ambiente,
                'nombre_ambiente' => $examenAmbiente->ambiente?->nombre_ambiente,
                'capacidad' => $examenAmbiente->ambiente?->capacidad,
                'ingresaron' => $delAmbiente,
                'total_ingresaron' => $delAmbiente->count(),
                'total_habilitados' => $habilitados->count(),
            ];
        })->values();

        $pendientes = $habilitados
            ->whereNotIn('id_estudiante', $idsIngresados)
            ->sortBy(fn ($habilitacion) => $habilitacion->estudiante?->apellidos)
            ->values()
            ->map(fn ($habilitacion) => [
                'id_estudiante' => $habilitacion->id_estudiante,
                'codigo_universitario' => $habilitacion->estudiante?->codigo_universitario,
                'nombres' => $habilitacion->estudiante?->nombres,
                'apellidos' => $habilitacion->estudiante?->apellidos,
            ]);

        return $this->responder($request, [
            'examenes' => $examenes,
            'examen' => $seleccionado,
            'ambientes' => $ambientes,
            'pendientes' => $pendientes,
            'resumen' => [
                'habilitados' => $habilitados->count(),
                'ingresaron' => count($idsIngresados),
                'pendientes' => $pendientes->count(),
                'estado' => $seleccionado->estado_actual,
                'cerrado' => $seleccionado->estado_horario === 'finalizado'
                    || in_array($seleccionado->estado, ['cancelado', 'anulado'], true),
            ],
        ]);
    }

    private function responder(Request $request, array $datos)
    {
        if (app()->runningUnitTests() || $request->wantsJson()) {
            return response()->json($datos);
        }

        return Inertia::render('Control/PanelAsistencia', $datos);
    }
}
