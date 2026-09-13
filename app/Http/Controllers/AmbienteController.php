<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAmbienteRequest;
use App\Models\Ambiente;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class AmbienteController extends Controller
{
    public function index()
    {
        $ambientes = Ambiente::query()
            ->select(['id_ambiente', 'nombre_ambiente', 'capacidad'])
            ->orderBy('id_ambiente')
            ->paginate(15);

        // Al integrar la vista, usar Inertia::render conservando la prop paginada 'ambientes'.
        return response()->json(['ambientes' => $ambientes]);
    }

    public function store(StoreAmbienteRequest $request)
    {
        try {
            DB::transaction(fn () => Ambiente::create($request->validated()));
        } catch (QueryException $e) {
            // La restricción única protege frente a registros simultáneos del mismo nombre.
            if ($e->getCode() === '23505' && str_contains($e->getMessage(), 'ambiente_nombre_ambiente_unique')) {
                return back()->withErrors([
                    'nombre_ambiente' => 'Ya existe un ambiente con ese nombre',
                ])->withInput();
            }

            throw $e;
        }

        return back()->with('success', 'Ambiente registrado correctamente.');
    }
}
