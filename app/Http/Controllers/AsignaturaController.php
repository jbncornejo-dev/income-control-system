<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAsignaturaRequest;
use App\Models\Asignatura;
use Illuminate\Database\QueryException;

class AsignaturaController extends Controller
{
    public function index()
    {
        $asignaturas = Asignatura::query()
            ->select(['id_asignatura', 'nombre_asignatura'])
            ->orderBy('id_asignatura')
            ->paginate(15);

        // Integración frontend: cuando exista la página Vue, sustituir esta respuesta
        // por Inertia::render con la página acordada y conservar la prop 'asignaturas'.
        return response()->json(['asignaturas' => $asignaturas]);
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
