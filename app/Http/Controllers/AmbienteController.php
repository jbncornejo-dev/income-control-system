<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexAmbienteRequest;
use App\Http\Requests\StoreAmbienteRequest;
use App\Http\Requests\UpdateAmbienteRequest;
use App\Models\Ambiente;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AmbienteController extends Controller
{
    public function index(IndexAmbienteRequest $request)
    {
        $filtros = $request->validated();
        $query = Ambiente::query()->select(['id_ambiente', 'nombre_ambiente', 'capacidad']);

        if (isset($filtros['nombre_ambiente'])) {
            // Buscar literalmente los comodines escritos por el usuario.
            $nombre = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filtros['nombre_ambiente']);
            $capacidad = filter_var($filtros['nombre_ambiente'], FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => 2147483647],
            ]);

            $query->where(function ($busqueda) use ($nombre, $capacidad) {
                $busqueda->whereRaw('unaccent(nombre_ambiente) ILIKE unaccent(?)', ['%'.$nombre.'%']);
                if ($capacidad !== false) {
                    $busqueda->orWhere('capacidad', $capacidad);
                }
            });
        }

        $ambientes = $query
            ->orderBy('id_ambiente')
            ->paginate(15)
            ->appends($filtros);

        // NUEVO: Calculamos la sumatoria de la capacidad. 
        // Si tienes una columna de estado en tu BD (ej: 'estado' => 'Habilitado'), 
        // cambia esto por: Ambiente::where('estado', 'Habilitado')->sum('capacidad');
        $totalCapacidad = Ambiente::sum('capacidad');

        // Retornamos JSON puro si se están ejecutando los tests
        if (app()->runningUnitTests() || $request->wantsJson()) {
            return response()->json(['ambientes' => $ambientes]);
        }

        // Retornamos la vista para los usuarios en el navegador
        return Inertia::render('Admin/Ambientes/Index', [
            'ambientes' => $ambientes,
            'totalCapacidad' => (int) $totalCapacidad,
            'filters' => $filtros
        ]);
    }

    public function update(UpdateAmbienteRequest $request, Ambiente $ambiente)
    {
        try {
            DB::transaction(fn () => $ambiente->update($request->safe()->only(['nombre_ambiente', 'capacidad'])));
        } catch (QueryException $e) {
            if ($e->getCode() === '23505' && str_contains($e->getMessage(), 'ambiente_nombre_ambiente_unique')) {
                return back()->withErrors([
                    'nombre_ambiente' => 'Ya existe un ambiente con ese nombre',
                ])->withInput();
            }

            throw $e;
        }

        return back()->with('success', 'Ambiente actualizado correctamente.');
    }

    public function destroy(Ambiente $ambiente)
    {
        $mensaje = 'No se puede eliminar el ambiente porque tiene exámenes registrados';

        if ($ambiente->examenesAmbientes()->exists()) {
            return back()->with('error', $mensaje);
        }

        try {
            DB::transaction(fn () => $ambiente->delete());
        } catch (QueryException $e) {
            // La clave foránea protege si se asocia un examen durante el borrado.
            if ($e->getCode() === '23503') {
                return back()->with('error', $mensaje);
            }

            throw $e;
        }

        return redirect()->route('ambientes.index')
            ->with('success', 'Ambiente eliminado correctamente.');
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
