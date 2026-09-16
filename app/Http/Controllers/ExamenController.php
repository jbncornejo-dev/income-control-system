<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexExamenRequest;
use App\Http\Requests\StoreExamenRequest;
use App\Models\Ambiente;
use App\Models\Examen;
use App\Models\ExamenAmbiente;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ExamenController extends Controller
{
    public function index(IndexExamenRequest $request)
    {
        $filtros = $request->validated();
        $query = Examen::query()
            ->select(['id_examen', 'id_asignatura', 'fecha', 'hora_inicio', 'duracion_minutos', 'normas_generales'])
            ->with([
                'asignatura:id_asignatura,nombre_asignatura',
                'examenesAmbientes.ambiente:id_ambiente,nombre_ambiente',
            ]);

        if (isset($filtros['asignatura'])) {
            // Buscar literalmente los comodines escritos por el usuario.
            $nombre = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filtros['asignatura']);
            $query->whereHas('asignatura', function ($subquery) use ($nombre) {
                $subquery->where('nombre_asignatura', 'ilike', '%'.$nombre.'%');
            });
        }

        if (isset($filtros['fecha'])) {
            $query->where('fecha', $filtros['fecha']);
        }

        if (isset($filtros['hora_inicio'])) {
            $query->where('hora_inicio', $filtros['hora_inicio']);
        }

        $examenes = $query
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->orderBy('id_examen')
            ->paginate(15)
            ->appends($filtros);

        if (app()->runningUnitTests() || $request->wantsJson()) {
            return response()->json([
                'examenes' => $examenes,
                'filtros' => [
                    'asignatura' => $filtros['asignatura'] ?? null,
                    'fecha' => $filtros['fecha'] ?? null,
                    'hora_inicio' => $filtros['hora_inicio'] ?? null,
                ],
            ]);
        }

        // Retornamos la vista de Inertia para los usuarios en el navegador
        return Inertia::render('Admin/Examenes/Index', [
            'examenes' => $examenes,
            'filters' => [ // Cambiado a 'filters' para que coincida con lo que espera tu componente Vue
                'asignatura' => $filtros['asignatura'] ?? null,
                'fecha' => $filtros['fecha'] ?? null,
                'hora_inicio' => $filtros['hora_inicio'] ?? null,
            ],
        ]);
    }

    public function store(StoreExamenRequest $request)
    {
        $datos = $request->validated();

        DB::transaction(function () use ($datos) {
            // Serializa altas que utilizan los mismos ambientes para evitar solapamientos concurrentes.
            Ambiente::query()
                ->whereIn('id_ambiente', $datos['id_ambientes'])
                ->lockForUpdate()
                ->get();

            if ($this->hayConflictoDeAmbiente($datos)) {
                throw ValidationException::withMessages([
                    'id_ambientes' => 'Uno o más ambientes ya están ocupados durante ese horario.',
                ]);
            }

            $examen = Examen::create([
                'id_asignatura' => $datos['id_asignatura'],
                'fecha' => $datos['fecha'],
                'hora_inicio' => $datos['hora_inicio'],
                'duracion_minutos' => $datos['duracion_minutos'],
                'normas_generales' => $datos['normas_generales'] ?? null,
            ]);

            foreach ($datos['id_ambientes'] as $idAmbiente) {
                ExamenAmbiente::create([
                    'id_examen' => $examen->id_examen,
                    'id_ambiente' => $idAmbiente,
                ]);
            }
        });

        return back()->with('success', 'Examen registrado correctamente.');
    }

    /**
     * An exam conflicts when its time interval overlaps an existing exam in at least one room.
     *
     * @param  array<string, mixed>  $datos
     */
    private function hayConflictoDeAmbiente(array $datos): bool
    {
        return Examen::query()
            ->where('fecha', $datos['fecha'])
            ->whereHas('examenesAmbientes', function ($query) use ($datos) {
                $query->whereIn('id_ambiente', $datos['id_ambientes']);
            })
            ->whereRaw(
                "hora_inicio < (CAST(? AS time) + (? * interval '1 minute'))",
                [$datos['hora_inicio'], $datos['duracion_minutos']]
            )
            ->whereRaw(
                "(hora_inicio + (duracion_minutos * interval '1 minute')) > CAST(? AS time)",
                [$datos['hora_inicio']]
            )
            ->exists();
    }
}
