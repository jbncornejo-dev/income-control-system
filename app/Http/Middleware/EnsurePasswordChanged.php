<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    /**
     * Exige el cambio de la contraseña inicial temporal antes de usar el panel.
     *
     * El redirect del login ya dirige al cambio, pero no basta: si el flag
     * sigue activo, ninguna ruta protegida del panel debe aceptar al usuario
     * (podría saltarse la obligación entrando directo por URL).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->esCuentaEstudiantePendiente()) {
            return redirect()->route('cambiar-password.show');
        }

        return $next($request);
    }
}
