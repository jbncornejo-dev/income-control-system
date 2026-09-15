<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExamenRequest;
use App\Models\Ambiente;
use App\Models\Examen;
use App\Models\ExamenAmbiente;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamenController extends Controller
{
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
