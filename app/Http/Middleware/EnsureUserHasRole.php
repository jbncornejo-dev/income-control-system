<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Restrict a route to one or more role names stored in the rol table.
     *
     * @param  array<int, string>  $roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = $request->user()?->rol?->nombre_rol;

        if (! in_array($role, $roles, true) && ! $request->user()?->rol?->permiteRuta($request->route()->getName())) {
            abort(403);
        }

        return $next($request);
    }
}
