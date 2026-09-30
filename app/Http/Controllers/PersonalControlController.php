<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePersonalControlRequest;
use App\Models\Examen;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
}
