<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEstudianteRequest;
use App\Models\Estudiante;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $students = Estudiante::query()
            ->when($request->input('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('codigo_universitario', 'like', "%{$search}%")
                        ->orWhere('documento_identidad', 'like', "%{$search}%")
                        ->orWhere('nombres', 'ilike', "%{$search}%")
                        ->orWhere('apellidos', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('apellidos')
            ->paginate(15)
            ->withQueryString();

        // Mapea a formato esperado por el diseño Vue actual (ci, name, career, status)
        $mapped = $students->through(function ($s) {
            return [
                'id' => $s->id_estudiante,
                'ci' => $s->documento_identidad,
                'name' => trim($s->nombres.' '.$s->apellidos),
                'career' => $s->codigo_universitario,
                'status' => 'active',
                'statusText' => 'Habilitada',
            ];
        });

        return Inertia::render('Estudiantes/Index', [
            'estudiantes' => $mapped,
        ]);
    }

    public function store(StoreEstudianteRequest $request)
    {
        try {
            Estudiante::create($request->validated());
        } catch (QueryException $e) {
            // 23505 = unique_violation en Postgres (race condition)
            if ($e->getCode() === '23505') {
                $message = $e->getMessage();

                if (str_contains($message, 'codigo_universitario')) {
                    return back()->withErrors(['codigo_universitario' => 'El código universitario ya está registrado.'])->withInput();
                }

                if (str_contains($message, 'documento_identidad')) {
                    return back()->withErrors(['documento_identidad' => 'El documento de identidad ya está registrado.'])->withInput();
                }

                if (str_contains($message, 'codigo_qr')) {
                    return back()->withErrors(['codigo_qr' => 'El código QR ya está registrado.'])->withInput();
                }

                return back()->withErrors(['codigo_universitario' => 'Registro duplicado.'])->withInput();
            }

            throw $e;
        }

        return redirect()->route('estudiantes.index')->with('success', 'Estudiante registrado correctamente.');
    }
}
