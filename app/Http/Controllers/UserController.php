<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Estudiante;
use App\Models\Rol;
use App\Support\AdminCredentials;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
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

        // Búsqueda por email o nombre
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', '%' . $search . '%')
                  ->orWhere('name', 'like', '%' . $search . '%');
            });
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

        $rolEstudiante = Rol::where('nombre_rol', 'estudiante')->value('id_rol');

        if ((int) $data['id_rol'] === (int) $rolEstudiante) {
            DB::transaction(function () use ($data) {
                $estudiante = Estudiante::create([
                    'codigo_universitario' => $data['codigo_universitario'],
                    'documento_identidad' => $data['documento_identidad'],
                    'nombres' => $data['nombres'],
                    'apellidos' => $data['apellidos'],
                    'email' => $data['email'] ?? null,
                    'codigo_qr' => Estudiante::qrPayload($data['codigo_universitario']),
                ]);

                User::create([
                    'id_rol' => $data['id_rol'],
                    'id_estudiante' => $estudiante->id_estudiante,
                    'name' => trim($data['nombres'].' '.$data['apellidos']),
                    'username' => $data['codigo_universitario'],
                    'documento_identidad' => $data['documento_identidad'],
                    'email' => $data['email'] ?? null,
                    'email_verified_at' => now(),
                    'password' => Hash::make($data['documento_identidad']),
                    'debe_cambiar_password' => true,
                ]);
            });

            return redirect()->back()->with('success', 'Estudiante registrado correctamente. La contraseña inicial es su CI y deberá cambiarla en el primer ingreso.');
        }

        $data['password'] = Hash::make($data['password'] ?? $data['documento_identidad']);
        $data['name'] = trim($data['nombres'].' '.$data['apellidos']);
        $data['username'] ??= $data['codigo_universitario'];
        $data['debe_cambiar_password'] = true;

        User::create($data);

        return back()->with('success', 'Usuario creado. Deberá cambiar su contraseña en el primer ingreso.');
    }

    public function update(UpdateUserRequest $request, User $usuario)
    {
        DB::transaction(function () use ($request, $usuario) {
            $this->confirmarAdministradorActivo($request);
            $rolOriginal = $usuario->id_rol;
            $usuario->refresh();
            abort_if($usuario->id_rol !== $rolOriginal, 409, 'El rol de esta cuenta cambió. Vuelve a cargar la página.');
            $data = $request->validated();
            $rol = Rol::find($data['id_rol'] ?? $usuario->id_rol);
            $cambiaRol = (int) $rol?->id_rol !== (int) $usuario->id_rol;

            if ($cambiaRol && ($usuario->is($request->user()) || $usuario->id_estudiante !== null || $rol?->nombre_rol === 'estudiante')) {
                throw ValidationException::withMessages(['id_rol' => 'No puedes cambiar tu propio rol ni convertir una cuenta desde o hacia el rol estudiante.']);
            }

            $usuario->fill(array_filter($data, fn ($value) => $value !== null));
            $cambiaClave = $usuario->isDirty('password');

            if ($cambiaClave || $cambiaRol) {
                $usuario->forceFill(['remember_token' => null]);
            }
            if ($cambiaClave) {
                $usuario->debe_cambiar_password = ! $usuario->is($request->user());
            }
            $usuario->save();

            if (($cambiaClave || $cambiaRol) && ! $usuario->is($request->user())) {
                $usuario->revocarSesiones();
            }
        });

        return redirect()->back()->with('success', 'Usuario actualizado exitosamente.');
    }

    public function updatePassword(Request $request, User $usuario)
    {
        DB::transaction(function () use ($request, $usuario) {
            $this->confirmarAdministradorActivo($request);
            $usuario->refresh();
            $datos = $request->validate([
                'password' => [
                    'required', 'string', 'max:72', 'confirmed',
                    $usuario->rol?->requiereClaveSegura()
                        ? AdminCredentials::password()
                        : 'min:8',
                ],
            ]);

            $usuario->forceFill([
                'password' => $datos['password'],
                'debe_cambiar_password' => ! $usuario->is($request->user()),
                'remember_token' => null,
            ])->save();

            if (! $usuario->is($request->user())) {
                $usuario->revocarSesiones();
            }
        });

        return redirect()->back()->with('success', 'Contraseña actualizada exitosamente.');
    }

    public function destroy(Request $request, User $usuario)
    {
        if ($usuario->is($request->user())) {
            throw ValidationException::withMessages(['error' => 'No puedes desactivar tu propia cuenta.']);
        }

        DB::transaction(function () use ($request, $usuario) {
            $this->confirmarAdministradorActivo($request);
            $usuario->refresh()->forceFill(['activo' => false, 'remember_token' => null])->save();
            $usuario->revocarSesiones();
        });

        return back()->with('success', 'Usuario desactivado.');
    }

    public function activate(Request $request, User $usuario)
    {
        DB::transaction(function () use ($request, $usuario) {
            $this->confirmarAdministradorActivo($request);
            $usuario->refresh()->update(['activo' => true]);
        });

        return back()->with('success', 'Usuario reactivado.');
    }

    private function confirmarAdministradorActivo(Request $request): void
    {
        // Dos administradores no pueden desactivarse o degradarse mutuamente en simultáneo.
        Rol::where('nombre_rol', 'administrador')->lockForUpdate()->firstOrFail();
        $actor = $request->user()->fresh();
        abort_unless($actor?->rol?->nombre_rol === 'administrador' && $actor->puedeIngresar(), 403);
    }
}
