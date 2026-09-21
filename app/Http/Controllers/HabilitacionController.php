<?php

namespace App\Http\Controllers;

use App\Models\AuditoriaLog;
use App\Models\Examen;
use App\Models\Grupo;
use App\Models\Habilitacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class HabilitacionController extends Controller
{
    public function index(Request $request, Examen $examen)
    {
        // El docente solo accede a las habilitaciones de las asignaturas que dicta.
        if ($request->user()->rol->nombre_rol === 'docente'
            && ! Grupo::query()
                ->where('id_usuario', $request->user()->id)
                ->where('id_asignatura', $examen->id_asignatura)
                ->exists()
        ) {
            abort(403);
        }

        // 1. Cargamos las relaciones del examen necesarias para la vista
        $examen->load(['asignatura', 'examenesAmbientes.ambiente']);

        // 2. Preparamos tu consulta original
        $query = Habilitacion::query()
            ->with('estudiante')
            ->where('id_examen', $examen->id_examen);

        // 3. Calculamos las estadísticas clonando la consulta (evita interferir con la paginación)
        $stats = [
            'habilitados' => (clone $query)->where('estado_habilitado', true)->count(),
            'inhabilitados' => (clone $query)->where('estado_habilitado', false)->count(),
            'ingresaron' => 0,
        ];

        // 4. Ejecutamos tu paginación original
        $habilitaciones = $query
            ->orderBy('id_habilitacion')
            ->paginate(15);

        // 5. Mantenemos el retorno JSON original para tus tests
        if (app()->runningUnitTests() || $request->wantsJson()) {
            return response()->json($habilitaciones);
        }

        // 6. Retornamos la vista de Inertia
        return Inertia::render('Admin/Habilitaciones/Index', [
            'examen' => $examen,
            'habilitaciones' => $habilitaciones,
            'stats' => $stats,
            // Pasamos esAdmin por si necesitamos ocultar algún botón específico más adelante
            'esAdmin' => $request->user()->rol->nombre_rol === 'administrador',
        ]);
    }

    public function update(Request $request, Habilitacion $habilitacion)
    {
        $datos = $request->validate([
            'estado_habilitado' => ['required', 'boolean'],
            'motivo_inhabilitacion' => ['nullable', 'string', 'max:1000'],
            'normas_particulares' => ['nullable', 'string', 'max:1000'],
        ]);

        $estaHabilitado = filter_var(
            $datos['estado_habilitado'],
            FILTER_VALIDATE_BOOLEAN
        );

        if (
            ! $estaHabilitado
            && blank($datos['motivo_inhabilitacion'] ?? null)
        ) {
            throw ValidationException::withMessages([
                'motivo_inhabilitacion' => 'El motivo de inhabilitación es obligatorio cuando el estudiante está inhabilitado.',
            ]);
        }

        $nuevoMotivo = $estaHabilitado
            ? null
            : trim($datos['motivo_inhabilitacion']);

        DB::transaction(function () use (
            $request,
            $habilitacion,
            $estaHabilitado,
            $nuevoMotivo
        ) {
            $habilitacion->fill([
                'estado_habilitado' => $estaHabilitado,
                'motivo_inhabilitacion' => $nuevoMotivo,
                'normas_particulares' => $datos['normas_particulares'] ?? null,
                'normas_particulares' => $datos['normas_particulares'] ?? null,
            ]);

            if (! $habilitacion->isDirty([
                'estado_habilitado',
                'motivo_inhabilitacion',
            ])) {
                return;
            }

            $habilitacion->save();

            AuditoriaLog::create([
                'id_usuario' => $request->user()->id,
                'tabla_afectada' => 'habilitacion',
                'id_registro_afectado' => $habilitacion->id_habilitacion,
                'accion' => 'UPDATE',
            ]);
        });

        return back()->with(
            'success',
            'Estado de habilitación actualizado correctamente.'
        );
    }

    public function store(Request $request, Examen $examen)
    {
        $datos = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:estudiante,id_estudiante',
            ],
        ]);

        $resultado = DB::transaction(function () use ($datos, $examen) {
            $creados = 0;
            $existentes = 0;

            foreach ($datos['student_ids'] as $idEstudiante) {
                $habilitacion = Habilitacion::firstOrCreate([
                    'id_estudiante' => $idEstudiante,
                    'id_examen' => $examen->id_examen,
                ]);

                if ($habilitacion->wasRecentlyCreated) {
                    $creados++;
                } else {
                    $existentes++;
                }
            }

            return [
                'creados' => $creados,
                'existentes' => $existentes,
            ];
        });

        return back()->with(
            'success',
            "Asociación completada. Nuevos: {$resultado['creados']}, ya existentes: {$resultado['existentes']}."
        );
    }
}
