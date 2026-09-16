<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexExamenRequest;
use App\Http\Requests\StoreExamenRequest;
use App\Http\Requests\UpdateExamenRequest;
use App\Models\Ambiente;
use App\Models\Examen;
use App\Models\ExamenAmbiente;
use Illuminate\Database\QueryException;
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

        // Determinamos la vista según el rol del usuario autenticado
        $vista = auth()->user()->rol->nombre_rol === 'docente'
            ? 'Docente/Examenes/Index'
            : 'Admin/Examenes/Index';

        // Retornamos la vista de Inertia correspondiente
        return Inertia::render($vista, [
            'examenes' => $examenes,
            'filters' => [
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
    public function update(UpdateExamenRequest $request, Examen $examen)
    {
        $datos = $request->validated();

        try {
            DB::transaction(function () use ($examen, $datos) {
                // Horario efectivo: fusiona lo enviado con lo ya existente.
                $efectivo = [
                    'id_asignatura' => $datos['id_asignatura'] ?? $examen->id_asignatura,
                    'fecha' => $datos['fecha'] ?? $examen->fecha,
                    'hora_inicio' => $datos['hora_inicio'] ?? $examen->hora_inicio,
                    'duracion_minutos' => $datos['duracion_minutos'] ?? $examen->duracion_minutos,
                    'normas_generales' => array_key_exists('normas_generales', $datos)
                        ? $datos['normas_generales']
                        : $examen->normas_generales,
                ];

                $idAmbientes = array_key_exists('id_ambientes', $datos)
                    ? $datos['id_ambientes']
                    : $examen->examenesAmbientes()->pluck('id_ambiente')->all();

                // Serializa altas que utilizan los mismos ambientes para evitar solapamientos concurrentes.
                Ambiente::query()
                    ->whereIn('id_ambiente', $idAmbientes)
                    ->lockForUpdate()
                    ->get();

                // No se permite cambiar ambientes si ya hay ingresos registrados.
                if (array_key_exists('id_ambientes', $datos)
                    && $examen->examenesAmbientes()->whereHas('registrosIngreso')->exists()
                ) {
                    throw ValidationException::withMessages([
                        'id_ambientes' => 'No se pueden modificar los ambientes porque el examen ya tiene ingresos registrados.',
                    ]);
                }

                if ($this->hayConflictoDeAmbiente($efectivo, $examen->id_examen)) {
                    throw ValidationException::withMessages([
                        'id_ambientes' => 'Uno o más ambientes ya están ocupados durante ese horario.',
                    ]);
                }

                $examen->update($efectivo);

                if (array_key_exists('id_ambientes', $datos)) {
                    // Reemplazo total del pivot: borra las actuales y crea las nuevas.
                    $examen->examenesAmbientes()->delete();
                    foreach ($datos['id_ambientes'] as $idAmbiente) {
                        ExamenAmbiente::create([
                            'id_examen' => $examen->id_examen,
                            'id_ambiente' => $idAmbiente,
                        ]);
                    }
                }
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23503') {
                return back()->withErrors([
                    'id_ambientes' => 'No se pueden modificar los ambientes porque el examen ya tiene ingresos registrados.',
                ])->withInput();
            }
            throw $e;
        }

        return back()->with('success', 'Examen actualizado correctamente.');
    }

    private function hayConflictoDeAmbiente(array $datos, ?int $idExamenIgnorar = null): bool
    {
        return Examen::query()
            ->when($idExamenIgnorar !== null, function ($query) use ($idExamenIgnorar) {
                $query->where('id_examen', '!=', $idExamenIgnorar);
            })
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
