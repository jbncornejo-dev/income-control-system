<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAsignaturaRequest;
use App\Models\Asignatura;
use Illuminate\Database\QueryException;

class AsignaturaController extends Controller
{
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
