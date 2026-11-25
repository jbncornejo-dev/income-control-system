<?php

namespace App\Http\Controllers;

use App\Support\AdminCredentials;
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
            'password' => [
                'required', 'string', 'max:72', 'confirmed', 'different:password_actual',
                $request->user()->rol?->requiereClaveSegura()
                    ? AdminCredentials::password()
                    : 'min:8',
            ],
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
