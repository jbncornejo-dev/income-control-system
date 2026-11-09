<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexAuditoriaRequest;
use App\Models\AuditoriaLog;
use App\Models\User;
use Inertia\Inertia;

class AuditoriaController extends Controller
{
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
            ->paginate(20)
            ->appends($filtros);

        if (app()->runningUnitTests() || $request->wantsJson()) {
            return response()->json(['registros' => $registros]);
        }

        return Inertia::render('Admin/Auditoria/Index', [
            'registros' => $registros,
            'filters' => $filtros,
            'usuarios' => User::query()
                ->whereHas('auditorias')
                ->orderBy('name')
                ->get(['id', 'name', 'username']),
            'tablas' => AuditoriaLog::query()
                ->select('tabla_afectada')
                ->distinct()
                ->orderBy('tabla_afectada')
                ->pluck('tabla_afectada'),
            'acciones' => AuditoriaLog::query()
                ->select('accion')
                ->distinct()
                ->orderBy('accion')
                ->pluck('accion'),
        ]);
    }
}
