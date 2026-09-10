<?php

namespace App\Http\Controllers;

use App\Models\AuditoriaLog;
use App\Models\Examen;
use App\Models\Habilitacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HabilitacionController extends Controller
{

    public function index(Examen $examen)
    {
        $habilitaciones = Habilitacion::query()
            ->with('estudiante')
            ->where('id_examen', $examen->id_examen)
            ->orderBy('id_habilitacion')
            ->paginate(15);

        // La vista de habilitaciones aún no existe; se devuelve el listado paginado para su integración con frontend.
        return response()->json($habilitaciones);
    }

    public function update(Request $request, Habilitacion $habilitacion)
    {
        $datos = $request->validate([
            'estado_habilitado' => ['required', 'boolean'],
            'motivo_inhabilitacion' => ['nullable', 'string', 'max:1000'],
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
                'motivo_inhabilitacion'
                    => 'El motivo de inhabilitación es obligatorio cuando el estudiante está inhabilitado.',
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