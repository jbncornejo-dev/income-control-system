<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExamenController;
use App\Http\Controllers\HabilitacionController;
use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (!Auth::check()) {
        return Inertia::render('Welcome'); 
    }

    $user = Auth::user();

    $rutaDestino = match ($user->role) {
        'admin'      => 'admin.dashboard',
        'docente'    => 'docente.dashboard',
        'control'    => 'control.dashboard',
        'estudiante' => 'estudiante.dashboard',
        default      => null, // Asignamos null si el rol no coincide con ninguno
    };

    if (!$rutaDestino) {
        Auth::logout(); // Invalidamos la sesión por seguridad
        // Redirigimos al login enviando un mensaje de error a la variable de sesión
        return redirect()->route('login')->withErrors([
            'role' => 'Su cuenta no tiene un rol válido asignado. Comuníquese con administración.'
        ]);
    }

    return redirect()->route($rutaDestino);
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', function () {
        return Inertia::render('Auth/Login');
    })->name('login');

    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::middleware('role:administrador,docente,personal de control de ingreso')->group(function () {
        Route::get('/estudiantes', [StudentController::class, 'index'])->name('estudiantes.index');
    });

    Route::middleware('role:administrador')->group(function () {
        Route::post('/estudiantes', [StudentController::class, 'store'])->name('estudiantes.store');
        Route::post('/estudiantes/importar', [StudentController::class, 'importar'])->name('estudiantes.importar');
        Route::post('/examenes', [ExamenController::class, 'store'])->name('examenes.store');
    });

    Route::middleware('role:administrador,docente')->group(function () {
        // Asociar estudiantes a un examen
        Route::post(
            '/examenes/{examen}/habilitaciones',
            [HabilitacionController::class, 'store']
        )->name('habilitaciones.store');

        // Listar estudiantes asociados a un examen
        Route::get(
            '/examenes/{examen}/habilitaciones',
            [HabilitacionController::class, 'index']
        )->name('habilitaciones.index');

        // Cambiar estado de una habilitación
        Route::patch(
            '/habilitaciones/{habilitacion}',
            [HabilitacionController::class, 'update']
        )->name('habilitaciones.update');
    });

    Route::get('/access-denied', function () {
        return Inertia::render('Errors/403');
    })->name('access.denied');
});
