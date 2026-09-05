<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\HabilitacionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

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

    Route::get('/estudiantes', [StudentController::class, 'index'])->name('estudiantes.index');
    Route::post('/estudiantes', [StudentController::class, 'store'])->name('estudiantes.store');

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

    Route::get('/access-denied', function () {
        return Inertia::render('Errors/403');
    })->name('access.denied');
});
