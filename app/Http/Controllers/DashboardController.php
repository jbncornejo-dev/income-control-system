<?php

namespace App\Http\Controllers;

use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\User;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Display the admin dashboard.
     */
    public function admin()
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'estudiantes' => Estudiante::count(),
                'examenes' => Examen::count(),
                'asignaturas' => Asignatura::count(), // Keeping existing
                'ambientes' => Ambiente::count(),
                'usuarios' => User::count(),
            ],
            // Corregida la relación de ambientes para coincidir con tu controlador
            'proximosExamenes' => Examen::with(['asignatura', 'examenesAmbientes.ambiente'])
                ->orderBy('fecha', 'asc')
                ->take(4)
                ->get(),
        ]);
    }

    /**
     * Display the docente dashboard.
     */
    public function docente()
    {
        $user = auth()->user();

        $proximosExamenes = Examen::with(['asignatura', 'examenesAmbientes.ambiente'])
            ->withCount([
                'habilitaciones as hab_count' => function ($query) {
                    $query->where('estado_habilitado', true);
                },
                'habilitaciones as inhab_count' => function ($query) {
                    $query->where('estado_habilitado', false);
                },
            ])
            // Solo exámenes que cubren al menos uno de los grupos del docente.
            ->whereHas('grupos', fn ($query) => $query->where('grupo.id_usuario', $user->id))
            ->where(function ($query) {
                $query->where('fecha', '>', now()->toDateString())
                    ->orWhere(function ($q) {
                        $q->where('fecha', '=', now()->toDateString())
                            ->where('hora_inicio', '>', now()->toTimeString());
                    });
            })
            ->orderBy('fecha', 'asc')
            ->orderBy('hora_inicio', 'asc')
            ->take(3)
            ->get();

        return Inertia::render('Docente/Dashboard', [
            'stats' => [
                'examenes' => $proximosExamenes->count(),
                'habilitados' => (int) $proximosExamenes->sum('hab_count'),
                'inhabilitados' => (int) $proximosExamenes->sum('inhab_count'),
            ],
            'proximosExamenes' => $proximosExamenes,
        ]);
    }

    /**
     * Display the control dashboard.
     */
    public function control()
    {
        // Definición del intervalo de "fechas inmediatas"
        // Se define inicialmente como 1 día (hoy y mañana) para que el personal
        // pueda prever y preparar los ambientes de los exámenes próximos inmediatos.
        $diasVisibilidad = 1;

        $fechaInicio = now()->toDateString();
        $fechaFin = now()->addDays($diasVisibilidad)->toDateString();

        $examenesControl = Examen::with(['asignatura', 'examenesAmbientes.ambiente'])
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->orderBy('fecha', 'asc')
            ->orderBy('hora_inicio', 'asc')
            ->get();

        return Inertia::render('Control/Dashboard', [
            'stats' => [
                'hoy' => $examenesControl->where('fecha', $fechaInicio)->count(),
                // Los siguientes valores requerirán lógica de tiempo real y de la tabla registro_ingreso
                'en_curso' => 0,
                'ingresos' => 0,
            ],
            'examenes' => $examenesControl,
        ]);
    }
}
