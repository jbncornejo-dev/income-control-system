<?php

use App\Http\Controllers\AmbienteController;
use App\Http\Controllers\AsignaturaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExamenController;
use App\Http\Controllers\HabilitacionController;
use App\Http\Controllers\PeriodoTipoExamenController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TipoExamenController;
use App\Http\Controllers\UserController;
use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Examen;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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

    /*Route::get('/admin/dashboard', function () { return Inertia::render('Dashboard'); })->name('admin.dashboard');
    Route::get('/docente/dashboard', function () { return Inertia::render('Dashboard'); })->name('docente.dashboard');
    Route::get('/control/dashboard', function () { return Inertia::render('Dashboard'); })->name('control.dashboard');
    Route::get('/estudiante/dashboard', function () { return Inertia::render('Dashboard'); })->name('estudiante.dashboard');
    */
    // Keep generic dashboard route to prevent breaking hardcoded links
    Route::get('/dashboard', function () {
        $user = auth()->user();

        // 1. Verificación de seguridad inicial
        if (! $user->rol) {
            abort(403, 'No tienes un rol asignado en la base de datos.');
        }

        $nombreRol = $user->rol->nombre_rol;

        // 2. LÓGICA PARA EL ADMINISTRADOR
        if ($nombreRol === 'administrador') {
            return redirect()->route('admin.dashboard');
        }

        // 3. LÓGICA PARA EL DOCENTE
        if ($nombreRol === 'docente') {
            return redirect()->route('docente.dashboard');
        }

        // 4. LÓGICA PARA EL ESTUDIANTE
        if ($nombreRol === 'estudiante') {
            return Inertia::render('Estudiantes/Dashboard', [
                // Datos específicos del estudiante
            ]);
        }

        // 5. LÓGICA PARA CONTROL DE INGRESO
        if ($nombreRol === 'personal de control de ingreso') {
            return redirect()->route('control.dashboard');
        }

        abort(403, 'Tu rol no tiene un panel principal configurado.');

    })->middleware(['verified'])->name('dashboard');

    Route::middleware('role:administrador,docente,personal de control de ingreso')->group(function () {
        Route::get('/control/dashboard', [DashboardController::class, 'control'])->name('control.dashboard');
        Route::get('/estudiantes', [StudentController::class, 'index'])->name('estudiantes.index');
    });

    Route::middleware('role:administrador')->group(function () {
        Route::get('/admin/dashboard', [DashboardController::class, 'admin'])->name('admin.dashboard');
        Route::get('/usuarios', [UserController::class, 'index'])->name('usuarios.index');
        Route::post('/usuarios', [UserController::class, 'store'])->name('usuarios.store');
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
        // Eliminar únicamente exámenes sin inscripciones ni registros de ingreso; además borra sus ambientes asociados.
        Route::delete('/examenes/{examen}', [ExamenController::class, 'destroy'])->name('examenes.destroy');

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

        // Catálogo de tipos de examen (gestionado por el administrador) y plan
        // de evaluación por periodo: qué tipos rigen en cada gestión y en qué orden.
        Route::get('/tipos-examen', [TipoExamenController::class, 'index'])->name('tipos-examen.index');
        Route::post('/tipos-examen', [TipoExamenController::class, 'store'])->name('tipos-examen.store');
        Route::patch('/tipos-examen/{tipoExamen}', [TipoExamenController::class, 'update'])->name('tipos-examen.update');
        Route::delete('/tipos-examen/{tipoExamen}', [TipoExamenController::class, 'destroy'])->name('tipos-examen.destroy');
        // Plan por periodo: PUT con la lista completa [{id_tipo_examen, orden}, ...].
        Route::put('/periodos/{periodo}/tipos-examen', [PeriodoTipoExamenController::class, 'sync'])->name('periodos.tipos-examen');

        Route::put('/usuarios/{usuario}', [UserController::class, 'update'])->name('usuarios.update');
        Route::patch('/usuarios/{usuario}/password', [UserController::class, 'updatePassword'])->name('usuarios.password');
        Route::delete('/usuarios/{usuario}', [UserController::class, 'destroy'])->name('usuarios.destroy');
    });

    Route::middleware('role:administrador,docente')->group(function () {
        Route::get('/docente/dashboard', [DashboardController::class, 'docente'])->name('docente.dashboard');
        Route::get('/examenes', [ExamenController::class, 'index'])->name('examenes.index');
        // Registrar exámenes: la vista carga las asignaturas y ambientes disponibles.
        Route::get('/examenes/crear', [ExamenController::class, 'create'])->name('examenes.create');
        // Consultar ambientes libres/ocupados para una ventana de tiempo (formulario registro/edición).
        Route::get('/examenes/disponibilidad', [ExamenController::class, 'disponibilidad'])->name('examenes.disponibilidad');
        // Página de edición: comparte el mismo formulario que el registro, precargado con el examen.
        Route::get('/examenes/{examen}/editar', [ExamenController::class, 'edit'])->name('examenes.edit');
        Route::post('/examenes', [ExamenController::class, 'store'])->name('examenes.store');
        // Edición parcial (PATCH) de un examen existente.
        Route::patch('/examenes/{examen}', [ExamenController::class, 'update'])->name('examenes.update');
        // Cambiar el estado manual del examen: anular (cancelado), suspender o reanudar.
        Route::patch('/examenes/{examen}/estado', [ExamenController::class, 'cambiarEstado'])->name('examenes.estado');
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
        Route::patch('/examenes/{examen}/habilitaciones', [HabilitacionController::class, 'updateBulk'])
            ->name('habilitaciones.updateBulk');
    });

    Route::get('/access-denied', function () {
        return Inertia::render('Errors/403');
    })->name('access.denied');
});
