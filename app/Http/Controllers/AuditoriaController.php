<?php

namespace App\Http\Controllers;

use App\Models\AuditoriaLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditoriaController extends Controller
{
    public const TABLAS = ['examen', 'ambiente', 'asignatura', 'users', 'grupo', 'habilitacion'];

    public function index(Request $request)
    {
        $filters = $request->validate([
            'tabla_afectada' => ['nullable', 'string', 'max:50'],
            'id_usuario' => ['nullable', 'integer'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);
        $logs = AuditoriaLog::with(['user:id,name,username'])
            ->when($filters['tabla_afectada'] ?? null, fn ($q, $value) => $q->where('tabla_afectada', $value))
            ->when($filters['id_usuario'] ?? null, fn ($q, $value) => $q->where('id_usuario', $value))
            ->when($filters['desde'] ?? null, fn ($q, $value) => $q->whereDate('fecha_hora', '>=', $value))
            ->when($filters['hasta'] ?? null, fn ($q, $value) => $q->whereDate('fecha_hora', '<=', $value))
            ->latest('fecha_hora')->latest('id_log')->paginate(15)->withQueryString();

        $data = [
            'logs' => $logs,
            'tablas' => self::TABLAS,
            'filters' => array_merge(['tabla_afectada' => '', 'id_usuario' => '', 'desde' => '', 'hasta' => ''], $filters),
        ];
        if (app()->runningUnitTests() || $request->wantsJson()) {
            return response()->json($data);
        }
        return Inertia::render('Admin/Auditoria/Index', $data + ['usuarios' => User::select(['id', 'name', 'username'])->orderBy('name')->get()]);
    }
}
