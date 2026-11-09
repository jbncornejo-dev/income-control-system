<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexAuditoriaRequest;
use App\Models\AuditoriaLog;
use App\Models\User;
use Inertia\Inertia;

class AuditoriaController extends Controller
{
    public const TABLAS = ['examen', 'ambiente', 'asignatura', 'users', 'grupo', 'habilitacion'];

    public function index(IndexAuditoriaRequest $request)
    {
        $filtros = $request->validated();

        $registros = AuditoriaLog::query()
            ->with('user:id,name,username')
            ->when($filtros['id_usuario'] ?? null, fn ($query, $valor) => $query->where('id_usuario', $valor))
            ->when($filtros['tabla_afectada'] ?? null, fn ($query, $valor) => $query->where('tabla_afectada', $valor))
            ->when($filtros['accion'] ?? null, fn ($query, $valor) => $query->where('accion', $valor))
            ->when($filtros['desde'] ?? null, fn ($query, $valor) => $query->whereDate('fecha_hora', '>=', $valor))
            ->when($filtros['hasta'] ?? null, fn ($query, $valor) => $query->whereDate('fecha_hora', '<=', $valor))
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id_log')
            ->paginate(15)
            ->appends($filtros);

        $data = [
            'registros' => $registros,
            'logs' => $registros,
            'tablas' => self::TABLAS,
            'filters' => array_merge(['tabla_afectada' => '', 'id_usuario' => '', 'desde' => '', 'hasta' => '', 'accion' => ''], $filtros),
        ];

        if (app()->runningUnitTests() || $request->wantsJson()) {
            return response()->json($data);
        }

        return Inertia::render('Admin/Auditoria/Index', array_merge($data, [
            'usuarios' => User::query()
                ->whereHas('auditorias')
                ->orWhereIn('id', AuditoriaLog::select('id_usuario')->distinct())
                ->orderBy('name')
                ->get(['id', 'name', 'username']),
            'acciones' => AuditoriaLog::query()
                ->select('accion')
                ->distinct()
                ->orderBy('accion')
                ->pluck('accion'),
        ]));
    }
}
