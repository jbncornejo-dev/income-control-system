<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Asistencia - {{ $examen->asignatura->nombre_asignatura ?? 'Examen' }}</title>
    <style>
        @page {
            margin: 28px 32px 35px 32px;
        }
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1f2937;
            line-height: 1.4;
        }
        .header {
            border-bottom: 2px solid #002147;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .institution {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
            margin-bottom: 2px;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
            color: #002147;
            margin: 0 0 4px 0;
        }
        .subtitle {
            font-size: 11px;
            color: #4b5563;
            margin: 0;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }
        .meta-table td {
            padding: 6px 10px;
            font-size: 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        .meta-label {
            font-weight: bold;
            color: #475569;
            width: 18%;
        }
        .meta-value {
            color: #0f172a;
            width: 32%;
        }
        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #002147;
            border-left: 4px solid #002147;
            padding-left: 8px;
            margin: 16px 0 8px 0;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .stats-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .stats-grid td {
            padding: 8px;
            text-align: center;
            border: 1px solid #cbd5e1;
            background: #ffffff;
        }
        .stat-num {
            display: block;
            font-size: 16px;
            font-weight: bold;
            color: #002147;
        }
        .stat-label {
            font-size: 9px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 600;
        }
        .stat-success .stat-num { color: #059669; }
        .stat-warning .stat-num { color: #d97706; }
        .stat-danger .stat-num { color: #dc2626; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .data-table th {
            background-color: #002147;
            color: #ffffff;
            font-weight: bold;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 6px 8px;
            text-align: left;
            border: 1px solid #002147;
        }
        .data-table td {
            padding: 5px 8px;
            font-size: 10px;
            border: 1px solid #e2e8f0;
        }
        .data-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
        }
        .badge-present {
            background-color: #d1fae5;
            color: #065f46;
        }
        .badge-absent {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .badge-info {
            background-color: #e0f2fe;
            color: #0369a1;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .empty-text {
            color: #64748b;
            font-style: italic;
            text-align: center;
            padding: 10px;
        }
        .footer {
            margin-top: 20px;
            border-top: 1px solid #cbd5e1;
            padding-top: 8px;
            font-size: 9px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>

    <div class="header">
        <div class="institution">Universidad Mayor de San Simón · Sistema de Control de Ingreso y Evaluación</div>
        <h1 class="title">REPORTE DE ASISTENCIA A EXAMEN</h1>
        <p class="subtitle">Emisión oficial generada el {{ \Carbon\Carbon::now('America/La_Paz')->format('d/m/Y H:i:s') }}</p>
    </div>

    <!-- Metadatos del Examen -->
    <table class="meta-table">
        <tr>
            <td class="meta-label">Asignatura:</td>
            <td class="meta-value"><strong>{{ $examen->asignatura->nombre_asignatura ?? 'Sin Asignatura' }}</strong></td>
            <td class="meta-label">Tipo de Examen:</td>
            <td class="meta-value">{{ $examen->tipo->nombre ?? 'Ordinario' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Fecha del Examen:</td>
            <td class="meta-value">{{ $examen->fecha }}</td>
            <td class="meta-label">Hora de Inicio:</td>
            <td class="meta-value">{{ $examen->hora_inicio }} (Duración: {{ $examen->duracion_minutos }} min)</td>
        </tr>
        <tr>
            <td class="meta-label">Estado de Gestión:</td>
            <td class="meta-value">{{ ucfirst($resumen['estado'] ?? 'Programado') }}</td>
            <td class="meta-label">Condición:</td>
            <td class="meta-value">
                @if($resumen['cerrado'] ?? false)
                    <span class="badge badge-absent">Examen Cerrado</span>
                @else
                    <span class="badge badge-present">En Curso / Activo</span>
                @endif
            </td>
        </tr>
    </table>

    <!-- Tarjetas de Resumen General -->
    <table class="stats-grid">
        <tr>
            <td>
                <span class="stat-num">{{ $resumen['habilitados'] ?? 0 }}</span>
                <span class="stat-label">Total Habilitados</span>
            </td>
            <td class="stat-success">
                <span class="stat-num">{{ $resumen['ingresaron'] ?? 0 }}</span>
                <span class="stat-label">Presentes (Ingresados)</span>
            </td>
            <td class="stat-danger">
                <span class="stat-num">{{ $resumen['pendientes'] ?? 0 }}</span>
                <span class="stat-label">Ausentes (No Presentados)</span>
            </td>
            <td class="stat-warning">
                @php
                    $totalHab = $resumen['habilitados'] ?? 0;
                    $totalIng = $resumen['ingresaron'] ?? 0;
                    $pct = $totalHab > 0 ? round(($totalIng / $totalHab) * 100, 1) : 0;
                @endphp
                <span class="stat-num">{{ $pct }}%</span>
                <span class="stat-label">% de Asistencia</span>
            </td>
        </tr>
    </table>

    <!-- Resumen / Totales por Ambiente -->
    <div class="section-title">1. Resumen y Totales por Ambiente</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 40%;">Ambiente / Aula</th>
                <th style="width: 15%; text-align: center;">Capacidad</th>
                <th style="width: 20%; text-align: center;">Ingresados (Presentes)</th>
                <th style="width: 20%; text-align: center;">Ocupación</th>
            </tr>
        </thead>
        <tbody>
            @forelse($ambientes as $index => $amb)
                @php
                    $cap = $amb['capacidad'] ?? 0;
                    $ing = $amb['total_ingresaron'] ?? 0;
                    $pctAmb = $cap > 0 ? round(($ing / $cap) * 100, 1) : 0;
                @endphp
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td><strong>{{ $amb['nombre_ambiente'] }}</strong></td>
                    <td class="text-center">{{ $cap > 0 ? $cap : 'N/D' }}</td>
                    <td class="text-center"><strong>{{ $ing }}</strong></td>
                    <td class="text-center">{{ $cap > 0 ? $pctAmb . '%' : 'N/D' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="empty-text">No hay ambientes asignados a este examen.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Listado de Estudiantes Ingresados -->
    <div class="section-title">2. Estudiantes Ingresados (Presentes - {{ count($ingresados) }})</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 15%;">Código SIS</th>
                <th style="width: 15%;">Doc. Identidad</th>
                <th style="width: 35%;">Apellidos y Nombres</th>
                <th style="width: 15%;">Ambiente</th>
                <th style="width: 15%; text-align: center;">Hora Ingreso</th>
            </tr>
        </thead>
        <tbody>
            @forelse($ingresados as $idx => $ing)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td><code>{{ $ing['codigo_universitario'] }}</code></td>
                    <td>{{ $ing['documento_identidad'] ?? '—' }}</td>
                    <td>{{ $ing['apellidos'] }}, {{ $ing['nombres'] }}</td>
                    <td>{{ $ing['nombre_ambiente'] ?? 'Asignado' }}</td>
                    <td class="text-center" style="color: #065f46; font-weight: bold;">
                        {{ $ing['hora_ingreso_formateada'] }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty-text">No se registraron estudiantes ingresados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Listado de Estudiantes Ausentes -->
    <div class="section-title">3. Estudiantes Ausentes (No Presentados - {{ count($pendientes) }})</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 18%;">Código SIS</th>
                <th style="width: 18%;">Doc. Identidad</th>
                <th style="width: 44%;">Apellidos y Nombres</th>
                <th style="width: 15%; text-align: center;">Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pendientes as $idx => $pend)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td><code>{{ $pend['codigo_universitario'] }}</code></td>
                    <td>{{ $pend['documento_identidad'] ?? '—' }}</td>
                    <td>{{ $pend['apellidos'] }}, {{ $pend['nombres'] }}</td>
                    <td class="text-center">
                        <span class="badge badge-absent">Ausente</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="empty-text">¡Todos los estudiantes habilitados registraron su ingreso!</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Documento generado automáticamente por el Sistema de Control de Ingreso · Página 1
    </div>

</body>
</html>

