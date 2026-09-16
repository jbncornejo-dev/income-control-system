<?php

use App\Http\Controllers\AmbienteController;
use App\Http\Controllers\AsignaturaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExamenController;
use App\Http\Controllers\HabilitacionController;
use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Asignatura;
use App\Models\Ambiente;
use App\Models\User;

Route::get('/', function () {
    if (! Auth::check()) {
        return Inertia::render('Welcome');
    }
    
    // Si está autenticado, que el controlador de tráfico del dashboard se encargue
    return redirect()->route('dashboard');
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

    Route::get('/admin/dashboard', function () { return Inertia::render('Dashboard'); })->name('admin.dashboard');
    Route::get('/docente/dashboard', function () { return Inertia::render('Dashboard'); })->name('docente.dashboard');
    Route::get('/control/dashboard', function () { return Inertia::render('Dashboard'); })->name('control.dashboard');
    Route::get('/estudiante/dashboard', function () { return Inertia::render('Dashboard'); })->name('estudiante.dashboard');

    // Keep generic dashboard route to prevent breaking hardcoded links
    Route::get('/dashboard', function () {
    $user = auth()->user();

    // 1. Verificación de seguridad inicial
    if (!$user->rol) {
        abort(403, 'No tienes un rol asignado en la base de datos.');
    }

    // 2. LÓGICA PARA EL ADMINISTRADOR
    // Evaluamos el ID del rol a través de la relación
    if ($user->rol->id_rol === 1) { 
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'estudiantes' => Estudiante::count(),
                'examenes'    => Examen::count(),
                'asignaturas' => Asignatura::count(),
                'ambientes'   => Ambiente::count(),
                'usuarios'    => User::count(),
            ],
            'proximosExamenes' => Examen::with(['asignatura', 'ambientes'])
                ->orderBy('fecha', 'asc')
                ->take(4)
                ->get()
        ]);
    }

    // 3. LÓGICA PARA EL ESTUDIANTE
    if ($user->rol->id_rol === 2) { 
        return Inertia::render('Estudiantes/Dashboard', [
            // Aquí enviarás los datos específicos del estudiante
        ]);
    }

    // Si el id_rol no es 1 ni 2 (por ejemplo, docente), cae aquí
    abort(403, 'Tu rol no tiene un panel principal configurado.');

})->middleware(['verified'])->name('dashboard');

    Route::middleware('role:administrador,docente,personal de control de ingreso')->group(function () {
        Route::get('/estudiantes', [StudentController::class, 'index'])->name('estudiantes.index');
    });

    Route::middleware('role:administrador')->group(function () {
        Route::get('/usuarios', [App\Http\Controllers\UserController::class, 'index'])->name('usuarios.index');
        // Listar y buscar ambientes: /ambientes?nombre_ambiente=aula, con paginación de 15 registros.
        Route::get('/ambientes', [AmbienteController::class, 'index'])->name('ambientes.index');
        // Editar nombre y capacidad; el ID de la URL identifica el ambiente y no se modifica.
        Route::patch('/ambientes/{ambiente}', [AmbienteController::class, 'update'])->name('ambientes.update');
        // Eliminar únicamente ambientes sin exámenes relacionados.
        Route::delete('/ambientes/{ambiente}', [AmbienteController::class, 'destroy'])->name('ambientes.destroy');
        // Registrar ambientes con nombre único y capacidad positiva.
        Route::post('/ambientes', [AmbienteController::class, 'store'])->name('ambientes.store');
        Route::post('/estudiantes', [StudentController::class, 'store'])->name('estudiantes.store');
        Route::put('/estudiantes/{estudiante}', [StudentController::class, 'update'])->name('estudiantes.update');
        // Eliminar únicamente estudiantes sin habilitaciones, registros de ingreso o incidencias.
        Route::delete('/estudiantes/{estudiante}', [StudentController::class, 'destroy'])->name('estudiantes.destroy');
        Route::post('/estudiantes/importar', [StudentController::class, 'importar'])->name('estudiantes.importar');
        // Listar y buscar exámenes: /examenes?asignatura=cálculo&fecha=2026-09-20&hora_inicio=08:00, con paginación de 15 registros.
        Route::get('/examenes', [ExamenController::class, 'index'])->name('examenes.index');
        Route::post('/examenes', [ExamenController::class, 'store'])->name('examenes.store');
        // Esta ruta atiende el listado y la búsqueda mediante parámetros de consulta:
        // /asignaturas?id_asignatura=12&nombre_asignatura=cálculo
        // Ambos filtros son opcionales; no se necesita una ruta separada para buscar.
        Route::get('/asignaturas', [AsignaturaController::class, 'index'])->name('asignaturas.index');
        // Frontend: editar solo nombre_asignatura mediante PATCH; el ID de la URL identifica el registro.
        Route::patch('/asignaturas/{asignatura}', [AsignaturaController::class, 'update'])->name('asignaturas.update');
        // Eliminar únicamente asignaturas sin exámenes relacionados.
        Route::delete('/asignaturas/{asignatura}', [AsignaturaController::class, 'destroy'])->name('asignaturas.destroy');
        // Ruta para registrar asignaturas
        Route::post('/asignaturas', [AsignaturaController::class, 'store'])->name('asignaturas.store');
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
