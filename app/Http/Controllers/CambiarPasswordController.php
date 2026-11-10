<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class CambiarPasswordController extends Controller
{
    public function show()
    {
        return Inertia::render('Auth/CambiarPassword');
    }

    public function store(Request $request)
    {
        $request->validate([
            'password_actual' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($request->password_actual, $user->password)) {
            return back()->withErrors([
                'password_actual' => 'La contraseña actual no es correcta.',
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'debe_cambiar_password' => false,
        ]);

        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Contraseña actualizada correctamente.');
    }
}
