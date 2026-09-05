<?php

namespace App\Http\Controllers;

use App\Models\Examen;
use App\Models\Habilitacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HabilitacionController extends Controller
{

    public function index(Examen $examen)
    {
        $habilitaciones = Habilitacion::query()
            ->with('estudiante')
            ->where('id_examen', $examen->id_examen)
            ->paginate(15);

        return response()->json($habilitaciones);
       // La vista de habilitaciones aún no existe; se devuelve el listado paginado para su integración con frontend.
    }

    public function update(Request $request, Habilitacion $habilitacion)
    {
        $datos = $request->validate([
            'estado_habilitado' => ['required', 'boolean'],
        ]);

        $habilitacion->update([
            'estado_habilitado' => $datos['estado_habilitado'],
        ]);

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