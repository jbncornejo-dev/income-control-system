<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Hash;
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

    public function store(Request $request)
    {
        // 1. Validar la petición
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'id_rol' => 'required|integer', // Asegúrate de que el id_rol exista en tu tabla de roles
            'password' => 'required|string|min:8|confirmed', // 'confirmed' busca el campo 'password_confirmation' en Vue
        ]);

        // 2. Crear el usuario
        User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'id_rol' => $request->id_rol,
            'password' => Hash::make($request->password), // Encriptación obligatoria
        ]);

        // 3. Redirigir hacia atrás (Inertia actualizará la tabla automáticamente)
        return redirect()->back()->with('success', 'Usuario creado exitosamente.');
    }

    public function update(Request $request, User $usuario)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username,' . $usuario->id,
            'email' => 'required|string|email|max:255|unique:users,email,' . $usuario->id,
            'id_rol' => 'required|integer',
        ]);

        $usuario->update([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'id_rol' => $request->id_rol,
        ]);

        return redirect()->back()->with('success', 'Usuario actualizado exitosamente.');
    }

    public function updatePassword(Request $request, User $usuario)
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $usuario->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->back()->with('success', 'Contraseña actualizada exitosamente.');
    }

    public function destroy(User $usuario)
    {
        // Opcional: Proteger para que un administrador no pueda eliminarse a sí mismo
        if (auth()->id() === $usuario->id) {
            return redirect()->back()->withErrors(['error' => 'No puedes eliminar tu propia cuenta.']);
        }

        $usuario->delete();

        return redirect()->back()->with('success', 'Usuario eliminado exitosamente.');
    }
}