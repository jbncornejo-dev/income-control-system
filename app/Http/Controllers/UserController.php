<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Rol;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        // Consultamos los usuarios incluyendo la relación 'rol'
        // Utilizamos paginación para manejar el pie de tabla (ej. "9 de 9 usuarios")
        $usuarios = User::with('rol')
            ->orderBy('id', 'desc')
            ->paginate(15);

        // Consultamos los roles para llenar el select del filtro
        $roles = Rol::all();

        return Inertia::render('Admin/Usuarios/Index', [
            'usuarios' => $usuarios,
            'roles' => $roles,
            // Aquí puedes retornar los filtros aplicados si implementas la búsqueda
            'filters' => $request->only(['search', 'role'])
        ]);
    }
}