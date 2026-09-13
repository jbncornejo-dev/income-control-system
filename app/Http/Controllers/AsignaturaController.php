<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexAsignaturaRequest;
use App\Http\Requests\StoreAsignaturaRequest;
use App\Http\Requests\UpdateAsignaturaRequest;
use App\Models\Asignatura;
use Illuminate\Database\QueryException;

class AsignaturaController extends Controller
{
    public function index(IndexAsignaturaRequest $request)
    {
        $filtros = $request->validated();
        $query = Asignatura::query()->select(['id_asignatura', 'nombre_asignatura']);

        if (isset($filtros['id_asignatura'])) {
            $query->where('id_asignatura', $filtros['id_asignatura']);
        }

        if (isset($filtros['nombre_asignatura'])) {
            // Escapar los comodines de LIKE para buscar el texto literal del usuario.
            $nombre = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filtros['nombre_asignatura']);
            $query->where('nombre_asignatura', 'ilike', '%'.$nombre.'%');
        }

        $asignaturas = $query
            ->orderBy('id_asignatura')
            ->paginate(15)
            ->appends($filtros);

        // Integración frontend: cuando exista la página Vue, sustituir esta respuesta
        // por Inertia::render con la página acordada y conservar la prop 'asignaturas'.
        return response()->json(['asignaturas' => $asignaturas]);
    }

    public function update(UpdateAsignaturaRequest $request, Asignatura $asignatura)
    {
        $asignatura->update($request->safe()->only('nombre_asignatura'));

        // Integración frontend: el formulario Inertia puede enviar PATCH a esta ruta;
        // la redirección conserva el flujo y comparte el mensaje mediante flash.success.
        return back()->with('success', 'Asignatura actualizada correctamente.');
    }

    public function store(StoreAsignaturaRequest $request)
    {
        try {
            Asignatura::create($request->validated());
        } catch (QueryException $e) {
            if ($e->getCode() === '23505') {
                return back()
                    ->withErrors([
                        'nombre_asignatura' => 'Ya existe una asignatura con ese nombre',
                    ])
                    ->withInput();
            }

            throw $e;
        }

        return back()->with(
            'success',
            'Asignatura registrada correctamente.'
        );
    }
}
