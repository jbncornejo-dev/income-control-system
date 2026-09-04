<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $students = Student::query()
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
                'id' => $s->id,
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
}
