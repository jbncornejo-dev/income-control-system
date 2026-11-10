<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Histórico de Incidencias</title>
    <style>
        @page {
            margin: 28px 30px 35px 30px;
        }
        @include('reportes.partials.pdf-styles')
        body {
            font-size: 10px;
            line-height: 1.35;
        }
        .header {
            margin-bottom: 12px;
        }
        .institution {
            font-size: 9px;
        }
        .title {
            font-size: 17px;
            margin: 0 0 3px 0;
        }
        .subtitle {
            font-size: 10px;
        }
        .filters-box {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .filters-box td {
            padding: 5px 8px;
            font-size: 9px;
            border-bottom: 1px solid #e2e8f0;
        }
        .filter-label {
            font-weight: bold;
            color: #475569;
            width: 20%;
        }
        .filter-val {
            color: #0f172a;
            width: 30%;
        }
        .data-table th {
            letter-spacing: 0.03em;
            padding: 6px 6px;
        }
        .data-table td {
            padding: 5px 6px;
            font-size: 9px;
            vertical-align: top;
        }
        .badge {
            padding: 2px 5px;
            font-size: 8px;
        }
        .tipo-expulsion { background-color: #fee2e2; color: #991b1b; }
        .tipo-intento { background-color: #fef3c7; color: #92400e; }
        .tipo-identificacion { background-color: #e0f2fe; color: #075985; }
        .tipo-cambio { background-color: #ede9fe; color: #5b21b6; }
        .tipo-otro { background-color: #f1f5f9; color: #334155; }
        .empty-text {
            padding: 12px;
        }
        .footer {
            margin-top: 15px;
            padding-top: 6px;
            font-size: 8px;
        }
    </style>
</head>
<body>

    @include('reportes.partials.pdf-header', ['titulo' => 'REPORTE HISTÓRICO DE INCIDENCIAS'])

    <!-- Filtros Aplicados -->
    <table class="filters-box">
        <tr>
            <td class="filter-label">Rango de Fechas:</td>
            <td class="filter-val">
                @if(!empty($filtros['desde']) || !empty($filtros['hasta']))
                    {{ $filtros['desde'] ?? 'Inicio' }} al {{ $filtros['hasta'] ?? 'Actual' }}
                @else
                    Todos los registros
                @endif
            </td>
            <td class="filter-label">Examen Filtrado:</td>
            <td class="filter-val">
                {{ $examenFiltro ? ($examenFiltro->asignatura->nombre_asignatura . ' (' . $examenFiltro->fecha . ')') : 'Todos los exámenes' }}
            </td>
        </tr>
        <tr>
            <td class="filter-label">Tipo de Incidencia:</td>
            <td class="filter-val">{{ $filtros['tipo_incidencia'] ?? 'Todos los tipos' }}</td>
            <td class="filter-label">Total Coincidencias:</td>
            <td class="filter-val"><strong>{{ count($incidencias) }} registros</strong></td>
        </tr>
    </table>

    <!-- Tabla Detallada de Incidencias -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 14%;">Fecha / Hora</th>
                <th style="width: 18%;">Examen</th>
                <th style="width: 16%;">Tipo Incidencia</th>
                <th style="width: 20%;">Estudiante</th>
                <th style="width: 13%;">Reportado Por</th>
                <th style="width: 15%;">Motivo / Detalle</th>
            </tr>
        </thead>
        <tbody>
            @forelse($incidencias as $idx => $inc)
                @php
                    $tipoClass = match($inc->tipo_incidencia) {
                        'Expulsión' => 'tipo-expulsion',
                        'Intento indebido' => 'tipo-intento',
                        'Problema de identificación' => 'tipo-identificacion',
                        'Cambio de ambiente' => 'tipo-cambio',
                        default => 'tipo-otro'
                    };
                @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ \Carbon\Carbon::parse($inc->fecha_hora)->setTimezone('America/La_Paz')->format('d/m/Y H:i') }}</td>
                    <td>
                        <strong>{{ $inc->examen->asignatura->nombre_asignatura ?? 'Sin asignatura' }}</strong><br>
                        <span style="color:#64748b;">{{ $inc->examen->fecha ?? '' }}</span>
                    </td>
                    <td>
                        <span class="badge {{ $tipoClass }}">{{ $inc->tipo_incidencia }}</span>
                    </td>
                    <td>
                        @if($inc->estudiante)
                            <strong>{{ $inc->estudiante->apellidos }}, {{ $inc->estudiante->nombres }}</strong><br>
                            <code>SIS: {{ $inc->estudiante->codigo_universitario }}</code>
                        @else
                            <span style="color:#64748b;">Incidencia General</span>
                        @endif
                    </td>
                    <td>{{ $inc->user->name ?? $inc->user->username ?? 'Personal' }}</td>
                    <td>{{ $inc->descripcion_motivo }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="empty-text">No se encontraron incidencias que coincidan con los filtros seleccionados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @include('reportes.partials.pdf-footer')

</body>
</html>

