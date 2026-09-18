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
        $query = User::with('rol')->orderBy('id', 'desc');

        // Filtro por rol (soporta 'id_rol' o 'role' según convención del frontend)
        if ($request->filled('id_rol')) {
            $query->where('id_rol', $request->id_rol);
        } elseif ($request->filled('role')) {
            $query->where('id_rol', $request->role);
        }

        // Búsqueda por email
        if ($request->filled('search')) {
            $query->where('email', 'like', '%' . $request->search . '%');
        }

        // Paginar y anexar los parámetros de la URL para no perderlos al cambiar de página
        $usuarios = $query->paginate(15)->withQueryString();

        // Consultamos los roles para llenar el select del filtro
        $roles = Rol::all();

        return Inertia::render('Admin/Usuarios/Index', [
            'usuarios' => $usuarios,
            'roles' => $roles,
            'filters' => $request->only(['search', 'role', 'id_rol'])
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

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

        return redirect()->back()->with('success', 'Usuario creado correctamente.');
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();
        
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->back()->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'delete' => 'No puedes eliminar tu propio usuario.'
            ]);
        }

        $user->delete();

        return redirect()->back()->with('success', 'Usuario eliminado correctamente.');
    }
}