<?php

namespace App\Http\Controllers;

use App\Models\Estudiante;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'identificador' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $limite = 'login:ip:'.$request->ip();
        if (RateLimiter::tooManyAttempts($limite, 20)) {
            return back()->withErrors(['identificador' => 'Demasiados intentos. Intenta de nuevo en un minuto.']);
        }

        $user = $this->resolverUsuarioPorIdentificador($request->input('identificador'));
        $limiteCuenta = $user ? 'login:cuenta:'.$user->id : null;
        if ($limiteCuenta && RateLimiter::tooManyAttempts($limiteCuenta, 10)) {
            return back()->withErrors(['identificador' => 'Demasiados intentos. Intenta de nuevo en un minuto.']);
        }

        $claveAdminDebil = $user?->rol?->nombre_rol === 'administrador'
            && in_array($request->password, ['pass', $user->documento_identidad], true);
        $cuentaDemo = in_array($user?->username, ['docente', 'docente2', 'control', 'estudiante'], true);
        $demoFueraDeLocal = ! app()->isLocal() && $cuentaDemo && $request->password === 'pass';

        if (! $user?->puedeIngresar() || $claveAdminDebil || $demoFueraDeLocal || ! Auth::attempt(['id' => $user->id, 'activo' => true, 'password' => $request->password], $request->boolean('remember'))) {
            RateLimiter::hit($limite, 60);
            if ($limiteCuenta) {
                RateLimiter::hit($limiteCuenta, 60);
            }
            return back()->withErrors([
                'identificador' => 'Credenciales inválidas.',
            ])->onlyInput('identificador');
        }

        RateLimiter::clear($limiteCuenta);
        $request->session()->regenerate();

        // Las cuentas con contraseña temporal deben cambiarla antes de usar el panel.
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

        return $estudiante?->user
            ?? User::query()->where('documento_identidad', $identificador)->first();
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
