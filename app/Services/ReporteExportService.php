<?php

namespace App\Services;

use App\Models\Examen;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ReporteExportService
{
    /**
     * Generar reporte de asistencia de un examen en formato PDF.
     */
    public function exportarAsistenciaPdf(Examen $examen, array $datos): Response
    {
        $nombreAsignatura = str_replace(' ', '_', $examen->asignatura->nombre_asignatura ?? 'examen');
        $filename = "reporte_asistencia_{$nombreAsignatura}_{$examen->fecha}.pdf";

        $pdf = Pdf::loadView('reportes.asistencia_pdf', [
            'examen' => $examen,
            'ambientes' => $datos['ambientes'],
            'ingresados' => $datos['ingresados'],
            'pendientes' => $datos['pendientes'],
            'resumen' => $datos['resumen'],
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }

    /**
     * Generar reporte de asistencia de un examen en formato Excel (.xlsx).
     */
    public function exportarAsistenciaExcel(Examen $examen, array $datos): BinaryFileResponse
    {
        $nombreAsignatura = str_replace(' ', '_', $examen->asignatura->nombre_asignatura ?? 'examen');
        $filename = "reporte_asistencia_{$nombreAsignatura}_{$examen->fecha}.xlsx";

        $tempFile = tempnam(sys_get_temp_dir(), 'asistencia_');

        $writer = new Writer();
        $writer->openToFile($tempFile);

        // Estilos
        $tituloStyle = (new Style())
            ->setFontBold()
            ->setFontSize(13)
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor('002147');

        $seccionStyle = (new Style())
            ->setFontBold()
            ->setFontSize(11)
            ->setFontColor('002147')
            ->setBackgroundColor('E2E8F0');

        $headerTableStyle = (new Style())
            ->setFontBold()
            ->setFontSize(10)
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor('002147');

        $boldStyle = (new Style())->setFontBold();
        $horaStyle = (new Style())->setFontBold()->setFontColor('059669');

        // 1. TÍTULO Y CABECERA DEL EXAMEN
        $writer->addRow(Row::fromValues([
            'REPORTE DE ASISTENCIA A EXAMEN - UNIVERSIDAD MAYOR DE SAN SIMÓN'
        ], $tituloStyle));

        $writer->addRow(Row::fromValues([
            'Generado:',
            Carbon::now('America/La_Paz')->format('d/m/Y H:i:s'),
        ]));

        $writer->addRow(Row::fromValues(['']));

        $writer->addRow(Row::fromValues(['DATOS DEL EXAMEN'], $seccionStyle));
        $writer->addRow(Row::fromValues(['Asignatura:', $examen->asignatura->nombre_asignatura ?? 'Sin asignatura'], $boldStyle));
        $writer->addRow(Row::fromValues(['Tipo de Examen:', $examen->tipo->nombre ?? 'Ordinario']));
        $writer->addRow(Row::fromValues(['Fecha del Examen:', $examen->fecha]));
        $writer->addRow(Row::fromValues(['Hora Inicio y Duración:', "{$examen->hora_inicio} ({$examen->duracion_minutos} minutos)"]));
        $writer->addRow(Row::fromValues(['Estado de Gestión:', ucfirst($datos['resumen']['estado'] ?? 'Programado')]));
        $writer->addRow(Row::fromValues(['Condición:', ($datos['resumen']['cerrado'] ?? false) ? 'Cerrado' : 'En Curso / Activo']));

        $writer->addRow(Row::fromValues(['']));

        // 2. RESUMEN GENERAL DE ASISTENCIA
        $totalHab = $datos['resumen']['habilitados'] ?? 0;
        $totalIng = $datos['resumen']['ingresaron'] ?? 0;
        $totalPen = $datos['resumen']['pendientes'] ?? 0;
        $pctAsist = $totalHab > 0 ? round(($totalIng / $totalHab) * 100, 1) : 0;

        $writer->addRow(Row::fromValues(['RESUMEN GENERAL'], $seccionStyle));
        $writer->addRow(Row::fromValues(['Total Habilitados', 'Ingresados (Presentes)', 'Ausentes (Pendientes)', '% Asistencia'], $headerTableStyle));
        $writer->addRow(Row::fromValues([$totalHab, $totalIng, $totalPen, "{$pctAsist}%"], $boldStyle));

        $writer->addRow(Row::fromValues(['']));

        // 3. TOTALES POR AMBIENTE
        $writer->addRow(Row::fromValues(['1. RESUMEN Y TOTALES POR AMBIENTE'], $seccionStyle));
        $writer->addRow(Row::fromValues(['#', 'Ambiente / Aula', 'Capacidad', 'Total Ingresados (Presentes)', '% Ocupación'], $headerTableStyle));

        foreach ($datos['ambientes'] as $idx => $amb) {
            $cap = $amb['capacidad'] ?? 0;
            $ing = $amb['total_ingresaron'] ?? 0;
            $pctAmb = $cap > 0 ? round(($ing / $cap) * 100, 1) . '%' : 'N/D';

            $writer->addRow(Row::fromValues([
                $idx + 1,
                $amb['nombre_ambiente'],
                $cap > 0 ? $cap : 'N/D',
                $ing,
                $pctAmb,
            ]));
        }

        $writer->addRow(Row::fromValues(['']));

        // 4. LISTADO DETALLADO DE ESTUDIANTES INGRESADOS (CON HORA DE INGRESO)
        $writer->addRow(Row::fromValues(['2. ESTUDIANTES INGRESADOS (PRESENTES - ' . count($datos['ingresados']) . ')'], $seccionStyle));
        $writer->addRow(Row::fromValues(['#', 'Código SIS', 'Doc. Identidad', 'Apellidos y Nombres', 'Ambiente', 'Hora Exacta de Ingreso'], $headerTableStyle));

        foreach ($datos['ingresados'] as $idx => $ing) {
            $writer->addRow(Row::fromValues([
                $idx + 1,
                $ing['codigo_universitario'],
                $ing['documento_identidad'] ?? '—',
                "{$ing['apellidos']}, {$ing['nombres']}",
                $ing['nombre_ambiente'] ?? 'Asignado',
                $ing['hora_ingreso_formateada'],
            ]));
        }

        if (count($datos['ingresados']) === 0) {
            $writer->addRow(Row::fromValues(['Sin estudiantes ingresados registrados']));
        }

        $writer->addRow(Row::fromValues(['']));

        // 5. LISTADO DETALLADO DE ESTUDIANTES AUSENTES
        $writer->addRow(Row::fromValues(['3. ESTUDIANTES AUSENTES (NO PRESENTADOS - ' . count($datos['pendientes']) . ')'], $seccionStyle));
        $writer->addRow(Row::fromValues(['#', 'Código SIS', 'Doc. Identidad', 'Apellidos y Nombres', 'Estado'], $headerTableStyle));

        foreach ($datos['pendientes'] as $idx => $pend) {
            $writer->addRow(Row::fromValues([
                $idx + 1,
                $pend['codigo_universitario'],
                $pend['documento_identidad'] ?? '—',
                "{$pend['apellidos']}, {$pend['nombres']}",
                'Ausente',
            ]));
        }

        if (count($datos['pendientes']) === 0) {
            $writer->addRow(Row::fromValues(['¡Todos los estudiantes habilitados registraron su ingreso!']));
        }

        $writer->close();

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Generar reporte histórico de incidencias en formato PDF.
     */
    public function exportarIncidenciasPdf(Collection $incidencias, array $filtros, ?Examen $examenFiltro = null): Response
    {
        $filename = 'reporte_incidencias_' . date('Ymd_His') . '.pdf';

        $pdf = Pdf::loadView('reportes.incidencias_pdf', [
            'incidencias' => $incidencias,
            'filtros' => $filtros,
            'examenFiltro' => $examenFiltro,
        ]);

        $pdf->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    /**
     * Generar reporte histórico de incidencias en formato Excel (.xlsx).
     */
    public function exportarIncidenciasExcel(Enumerable $incidencias, array $filtros, ?Examen $examenFiltro = null, ?int $total = null): BinaryFileResponse
    {
        $total ??= $incidencias->count();
        $filename = 'reporte_incidencias_' . date('Ymd_His') . '.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), 'incidencias_');

        $writer = new Writer();
        $writer->openToFile($tempFile);

        // Estilos
        $tituloStyle = (new Style())
            ->setFontBold()
            ->setFontSize(13)
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor('002147');

        $seccionStyle = (new Style())
            ->setFontBold()
            ->setFontSize(11)
            ->setFontColor('002147')
            ->setBackgroundColor('E2E8F0');

        $headerTableStyle = (new Style())
            ->setFontBold()
            ->setFontSize(10)
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor('002147');

        $boldStyle = (new Style())->setFontBold();

        // 1. TÍTULO Y FILTROS APLICADOS
        $writer->addRow(Row::fromValues([
            'REPORTE HISTÓRICO DE INCIDENCIAS - UNIVERSIDAD MAYOR DE SAN SIMÓN'
        ], $tituloStyle));

        $writer->addRow(Row::fromValues([
            'Generado:',
            Carbon::now('America/La_Paz')->format('d/m/Y H:i:s'),
        ]));

        $writer->addRow(Row::fromValues(['']));

        $writer->addRow(Row::fromValues(['FILTROS APLICADOS'], $seccionStyle));

        $rangoFechas = (!empty($filtros['desde']) || !empty($filtros['hasta']))
            ? (($filtros['desde'] ?? 'Inicio') . ' al ' . ($filtros['hasta'] ?? 'Actual'))
            : 'Todos los registros';
        $writer->addRow(Row::fromValues(['Rango de Fechas:', $rangoFechas]));

        $examenTxt = $examenFiltro
            ? "{$examenFiltro->asignatura->nombre_asignatura} ({$examenFiltro->fecha})"
            : 'Todos los exámenes';
        $writer->addRow(Row::fromValues(['Examen Filtrado:', $examenTxt]));

        $writer->addRow(Row::fromValues(['Tipo de Incidencia:', $filtros['tipo_incidencia'] ?? 'Todos los tipos']));
        $writer->addRow(Row::fromValues(['Total Registros Coincidentes:', $total], $boldStyle));

        $writer->addRow(Row::fromValues(['']));

        // 2. TABLA DE INCIDENCIAS
        $writer->addRow(Row::fromValues(['LISTADO DE INCIDENCIAS'], $seccionStyle));
        $writer->addRow(Row::fromValues([
            '#',
            'Fecha y Hora',
            'Asignatura / Examen',
            'Fecha Examen',
            'Tipo de Incidencia',
            'Código SIS',
            'Estudiante Afectado',
            'Reportado Por',
            'Descripción / Motivo',
        ], $headerTableStyle));

        foreach ($incidencias as $idx => $inc) {
            $fechaHora = Carbon::parse($inc->fecha_hora)->setTimezone('America/La_Paz')->format('d/m/Y H:i');
            $estudianteTxt = $inc->estudiante
                ? "{$inc->estudiante->apellidos}, {$inc->estudiante->nombres}"
                : 'General (Sin estudiante)';
            $codigoSis = $inc->estudiante?->codigo_universitario ?? 'N/A';
            $usuarioTxt = $inc->user->name ?? $inc->user->username ?? 'Personal de Control';

            $writer->addRow(Row::fromValues([
                $idx + 1,
                $fechaHora,
                $inc->examen->asignatura->nombre_asignatura ?? 'Sin asignatura',
                $inc->examen->fecha ?? 'N/A',
                $inc->tipo_incidencia,
                $codigoSis,
                $estudianteTxt,
                $usuarioTxt,
                $inc->descripcion_motivo,
            ]));
        }

        if ($total === 0) {
            $writer->addRow(Row::fromValues(['No se encontraron incidencias que coincidan con los filtros seleccionados.']));
        }

        $writer->close();

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}

