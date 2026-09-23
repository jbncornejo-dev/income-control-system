<?php

namespace App\Http\Controllers;

use App\Models\Estudiante;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'identificador' => ['required', 'string'],
            'password' => ['required'],
        ]);

        $user = $this->resolverUsuarioPorIdentificador($request->input('identificador'));

        if ($user === null || ! Auth::attempt(['id' => $user->id, 'password' => $request->password], $request->boolean('remember'))) {
            return back()->withErrors([
                'identificador' => 'Credenciales inválidas.',
            ])->onlyInput('identificador');
        }

        $request->session()->regenerate();

        // Las cuentas de estudiante recién creadas llevan una contraseña
        // inicial temporal: se obliga a cambiarla antes de usar el panel.
        if ($user->debe_cambiar_password) {
            return redirect()->route('cambiar-password.show');
        }

        // Todos los usuarios van a /dashboard.
        // El archivo web.php decidirá qué vista renderizar según su rol.
        return redirect()->intended(route('dashboard'));
    }

    /**
     * Resuelve la cuenta a partir de cualquiera de los identificadores
     * válidos: correo electrónico, código universitario (username) o
     * documento de identidad del estudiante.
     */
    private function resolverUsuarioPorIdentificador(string $identificador): ?User
    {
        $identificador = trim($identificador);

        if ($identificador === '') {
            return null;
        }

        if (str_contains($identificador, '@')) {
            // Login por correo: se consulta primero la cuenta y, si el correo
            // aún no está sincronizado en users, el del registro del estudiante.
            $usuario = User::query()->where('email', $identificador)->first();

            if ($usuario !== null) {
                return $usuario;
            }

            $estudiante = Estudiante::query()->where('email', $identificador)->first();

            return $estudiante?->user;
        }

        // Código universitario (el username de la cuenta del estudiante).
        $usuario = User::query()->where('username', $identificador)->first();

        if ($usuario !== null) {
            return $usuario;
        }

        // Documento de identidad del estudiante.
        $estudiante = Estudiante::query()->where('documento_identidad', $identificador)->first();

        return $estudiante?->user;
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
