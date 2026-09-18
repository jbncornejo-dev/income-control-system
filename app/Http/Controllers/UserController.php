<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Rol;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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

public function store(StoreUserRequest $request)
    {
        // 1. Obtenemos datos validados del Form Request de develop
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        // 2. Mantenemos la lógica de develop para autogenerar username si es necesario
        if (empty($data['username'])) {
            $baseUsername = Str::slug(explode(' ', trim($data['name']))[0].'_'.Str::before($data['email'], '@'));
            $baseUsername = Str::limit($baseUsername, 45, '');
            $username = $baseUsername;
            $suffix = 1;
            while (User::where('username', $username)->exists()) {
                $suffixStr = (string) $suffix++;
                $username = Str::limit($baseUsername, 50 - strlen($suffixStr), '').$suffixStr;
            }
            $data['username'] = $username;
        }

        User::create($data);

        return redirect()->back()->with('success', 'Usuario creado exitosamente.');
    }

    public function update(UpdateUserRequest $request, User $usuario)
    {
        // Usamos $usuario para mantener compatibilidad con tu Route Model Binding
        $data = $request->validated();
        
        // Mantenemos el fallback de develop por si otra vista actualiza la clave por aquí
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $usuario->update($data);

        return redirect()->back()->with('success', 'Usuario actualizado exitosamente.');
    }

    public function updatePassword(Request $request, User $usuario)
    {
        // Mantenemos tu método dedicado para el modal de Vue
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
        // Mantenemos tu protección de seguridad contra autoeliminación
        if (auth()->id() === $usuario->id) {
            return redirect()->back()->withErrors(['error' => 'No puedes eliminar tu propia cuenta.']);
        }

        $usuario->delete();

        return redirect()->back()->with('success', 'Usuario eliminado exitosamente.');
    }
}