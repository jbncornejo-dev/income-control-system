<?php

namespace App\Http\Controllers;

use App\Models\Examen;
use App\Models\Habilitacion;
use App\Models\RegistroIngreso;
use App\Models\ConflictoIngreso;
use App\Services\ReporteExportService;
use Carbon\Carbon;
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
                'conflictos' => [],
                'resumen' => null,
            ]);
        }

        $datosCompletos = $this->obtenerDatosCompletos($seleccionado);

        return $this->responder($request, [
            'examenes' => $examenes,
            'examen' => $seleccionado,
            'ambientes' => $datosCompletos['ambientes'],
            'pendientes' => $datosCompletos['pendientes'],
            'conflictos' => $datosCompletos['conflictos'],
            'resumen' => $datosCompletos['resumen'],
        ]);
    }

    /**
     * HU-26: Exportar reporte de asistencia a PDF.
     */
    public function exportarPdf(Request $request, ReporteExportService $exportService)
    {
        if (! in_array($request->user()?->rol?->nombre_rol, self::ROLES, true)) {
            abort(403, 'Acceso Denegado');
        }

        $idExamen = $request->query('id_examen');
        if (! $idExamen) {
            abort(400, 'Debe especificar el examen para exportar el reporte.');
        }

        $examen = Examen::with(['asignatura', 'tipo', 'examenesAmbientes.ambiente'])->findOrFail($idExamen);
        $datos = $this->obtenerDatosCompletos($examen);

        return $exportService->exportarAsistenciaPdf($examen, $datos);
    }

    /**
     * HU-26: Exportar reporte de asistencia a Excel (.xlsx).
     */
    public function exportarExcel(Request $request, ReporteExportService $exportService)
    {
        if (! in_array($request->user()?->rol?->nombre_rol, self::ROLES, true)) {
            abort(403, 'Acceso Denegado');
        }

        $idExamen = $request->query('id_examen');
        if (! $idExamen) {
            abort(400, 'Debe especificar el examen para exportar el reporte.');
        }

        $examen = Examen::with(['asignatura', 'tipo', 'examenesAmbientes.ambiente'])->findOrFail($idExamen);
        $datos = $this->obtenerDatosCompletos($examen);

        return $exportService->exportarAsistenciaExcel($examen, $datos);
    }

    /**
     * Obtener y consolidar los datos de asistencia del examen.
     */
    private function obtenerDatosCompletos(Examen $seleccionado): array
    {
        $conflictos = ConflictoIngreso::query()
            ->with(['estudiante:id_estudiante,nombres,apellidos,codigo_universitario', 'usuario:id,name'])
            ->whereHas('examenAmbiente', fn ($q) => $q->where('id_examen', $seleccionado->id_examen))
            ->get();

        $habilitados = Habilitacion::query()
            ->with('estudiante')
            ->where('id_examen', $seleccionado->id_examen)
            ->where('estado_habilitado', true)
            ->get();

        $ingresos = RegistroIngreso::query()
            ->with(['estudiante', 'examenAmbiente.ambiente'])
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
                    'documento_identidad' => $registro->estudiante?->documento_identidad,
                    'nombres' => $registro->estudiante?->nombres,
                    'apellidos' => $registro->estudiante?->apellidos,
                    'hora_ingreso' => $registro->fecha_hora_ingreso,
                    'hora_ingreso_formateada' => Carbon::parse($registro->fecha_hora_ingreso)->setTimezone('America/La_Paz')->format('H:i:s'),
                    'nombre_ambiente' => $examenAmbiente->ambiente?->nombre_ambiente,
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
                'documento_identidad' => $habilitacion->estudiante?->documento_identidad,
                'nombres' => $habilitacion->estudiante?->nombres,
                'apellidos' => $habilitacion->estudiante?->apellidos,
            ]);

        $ingresados = $ingresos->sortBy('fecha_hora_ingreso')->values()->map(function ($registro) {
            return [
                'id_estudiante' => $registro->id_estudiante,
                'codigo_universitario' => $registro->estudiante?->codigo_universitario,
                'documento_identidad' => $registro->estudiante?->documento_identidad,
                'nombres' => $registro->estudiante?->nombres,
                'apellidos' => $registro->estudiante?->apellidos,
                'nombre_ambiente' => $registro->examenAmbiente?->ambiente?->nombre_ambiente,
                'hora_ingreso' => $registro->fecha_hora_ingreso,
                'hora_ingreso_formateada' => Carbon::parse($registro->fecha_hora_ingreso)->setTimezone('America/La_Paz')->format('H:i:s'),
            ];
        });

        return [
            'ambientes' => $ambientes,
            'pendientes' => $pendientes,
            'ingresados' => $ingresados,
            'conflictos' => $conflictos,
            'resumen' => [
                'habilitados' => $habilitados->count(),
                'ingresaron' => count($idsIngresados),
                'pendientes' => $pendientes->count(),
                'estado' => $seleccionado->estado_actual,
                'cerrado' => $seleccionado->estado_horario === 'finalizado'
                    || in_array($seleccionado->estado, ['cancelado', 'anulado'], true),
            ],
        ];
    }

    private function responder(Request $request, array $datos)
    {
        if (app()->runningUnitTests() || $request->wantsJson()) {
            return response()->json($datos);
        }

        return Inertia::render('Control/PanelAsistencia', $datos);
    }

    public function exportar(Request $request, Examen $examen)
    {
        $habilitados = Habilitacion::with('estudiante')->where('id_examen', $examen->id_examen)
            ->where('estado_habilitado', true)->get();
        $ingresos = RegistroIngreso::with('estudiante')
            ->whereHas('examenAmbiente', fn ($q) => $q->where('id_examen', $examen->id_examen))->get();
        $ingresados = $ingresos->keyBy('id_estudiante');
        $rows = $habilitados->map(fn ($habilitacion) => [
            $habilitacion->estudiante?->codigo_universitario,
            trim(($habilitacion->estudiante?->nombres ?? '') . ' ' . ($habilitacion->estudiante?->apellidos ?? '')),
            $ingresados->get($habilitacion->id_estudiante)?->fecha_hora_ingreso ?? 'Ausente',
        ])->all();
        $headers = ['Código', 'Estudiante', 'Ingreso'];
        if ($request->query('formato') === 'pdf') {
            return ReportExporter::downloadPdf('asistencia-' . $examen->id_examen . '.pdf', 'Reporte de asistencia', [
                'Examen: ' . ($examen->asignatura?->nombre_asignatura ?? 'N/D'),
            ], [['heading' => 'Asistencia', 'headers' => $headers, 'rows' => $rows]]);
        }
        return ReportExporter::downloadExcel('asistencia-' . $examen->id_examen . '.xls', 'Reporte de asistencia', [[
            'name' => 'Asistencia', 'headers' => $headers, 'rows' => $rows,
        ]]);
    }
}
