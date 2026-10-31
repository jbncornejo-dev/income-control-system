<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePersonalControlRequest;
use App\Models\Examen;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Habilitacion;

class PersonalControlController extends Controller
{
    public function store(StorePersonalControlRequest $request, Examen $examen)
    {
        $idUsuario = (int) $request->validated()['id_usuario'];

        if ($examen->personalControl()->where('users.id', $idUsuario)->exists()) {
            return back()->withErrors(['id_usuario' => 'Este usuario ya está asignado a este examen']);
        }

        DB::transaction(fn () => $examen->personalControl()->attach($idUsuario));

        return back()->with('success', 'Personal de control asignado correctamente.');
    }

    public function destroy(Request $request, Examen $examen, User $usuario)
    {
        if (! in_array($request->user()?->rol?->nombre_rol, ['administrador', 'docente'], true)) {
            abort(403, 'Acceso Denegado');
        }

        DB::transaction(fn () => $examen->personalControl()->detach($usuario->id));

        return back()->with('success', 'Personal de control desasignado correctamente.');
    }
    
    public function repartoAutomatico(Request $request, Examen $examen)
    {
        if (! in_array($request->user()?->rol?->nombre_rol, ['administrador', 'docente'], true)) {
            abort(403, 'Acceso Denegado');
        }

        // Criterio 3: Verificar si el examen tiene personal de control asignado
        $personal = $examen->personalControl()->pluck('users.id')->toArray();
        if (empty($personal)) {
            return back()->withErrors([
                'reparto' => 'No se puede ejecutar el reparto porque el examen aún no tiene personal de control asignado.'
            ]);
        }

        // Criterio 1 y 2: Obtener estudiantes habilitados
        $habilitaciones = Habilitacion::where('id_examen', $examen->id_examen)
            ->where('estado_habilitado', true)
            ->orderBy('id_habilitacion')
            ->get();

        if ($habilitaciones->isEmpty()) {
            return back()->withErrors([
                'reparto' => 'No hay estudiantes habilitados en este examen para repartir.'
            ]);
        }

        // Reparto equitativo Round-Robin (garantiza diferencia máxima de 1)
        DB::transaction(function () use ($habilitaciones, $personal) {
            $totalPersonal = count($personal);
            foreach ($habilitaciones as $index => $habilitacion) {
                $idAgente = $personal[$index % $totalPersonal];
                $habilitacion->update([
                    'id_personal_control' => $idAgente
                ]);
            }
        });

        return back()->with('success', 'Reparto de estudiantes realizado exitosamente.');
    }

    public function asignarManual(Request $request, Habilitacion $habilitacion)
    {
        if (! in_array($request->user()?->rol?->nombre_rol, ['administrador', 'docente'], true)) {
            abort(403, 'Acceso Denegado');
        }

        $validated = $request->validate([
            'id_personal_control' => 'required|exists:users,id',
        ]);

        // Criterio 2: Reasignación manual puntual
        $habilitacion->update([
            'id_personal_control' => $validated['id_personal_control'],
        ]);

        return back()->with('success', 'Estudiante reasignado correctamente.');
    }
}
