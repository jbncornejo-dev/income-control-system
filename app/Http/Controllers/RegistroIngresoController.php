<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Examen;
use Carbon\Carbon;
use App\Models\Estudiante;
use App\Models\Habilitacion;
use App\Models\RegistroIngreso;
use App\Models\ExamenAmbiente;

class RegistroIngresoController extends Controller
{
    public function index()
    {
        // 1. Recuperar exámenes vigentes (desde la fecha actual) con sus relaciones
        $examenes = Examen::with(['asignatura', 'examenesAmbientes.ambiente'])
            ->whereDate('fecha', '>=', Carbon::today())
            // ->where('estado', '!=', 'Anulado') // Descomenta si manejas un estado de anulación
            ->get()
            ->map(function ($examen) {
                // 2. Mapear los datos para enviar una estructura limpia al frontend
                return [
                    'id_examen' => $examen->id_examen,
                    'asignatura' => $examen->asignatura->nombre_asignatura ?? 'Sin asignatura',
                    'fecha' => $examen->fecha,
                    'hora_inicio' => $examen->hora_inicio,
                    // Extraer los ambientes asociados mediante la tabla pivote (examen_ambiente)
                    'ambientes' => $examen->examenesAmbientes->map(function ($pivot) {
                        return [
                            'id_examen_ambiente' => $pivot->id_examen_ambiente, // ID necesario para el registro final
                            'id_ambiente' => $pivot->ambiente->id_ambiente ?? null,
                            'nombre_ambiente' => $pivot->ambiente->nombre_ambiente ?? 'Desconocido',
                        ];
                    })
                ];
            });

        // 3. Retornar la vista de Inertia con los datos
        return Inertia::render('Control/RegistroIngreso/Index', [
            'examenes' => $examenes
        ]);
    }
    // Método para validar al estudiante antes de confirmar el ingreso
    public function validarEstudiante(Request $request)
    {
        $request->validate([
            'id_examen' => 'required|integer',
            'dato_estudiante' => 'required|string'
        ]);

        $dato = $request->input('dato_estudiante');
        $idExamen = $request->input('id_examen');

        // Buscar por código, documento o QR
        $estudiante = Estudiante::where('codigo_universitario', $dato)
            ->orWhere('documento_identidad', $dato)
            ->orWhere('codigo_qr', $dato)
            ->first();

        if (!$estudiante) {
            return response()->json(['error' => 'No se encontró ningún estudiante con ese dato'], 404);
        }

        // Verificar si está habilitado
        $habilitacion = Habilitacion::where('id_estudiante', $estudiante->id_estudiante)
            ->where('id_examen', $idExamen)
            ->first();

        if (!$habilitacion || !$habilitacion->estado_habilitado) {
            $motivo = $habilitacion->motivo_inhabilitacion ?? 'El estudiante no está habilitado para rendir este examen';
            return response()->json(['error' => $motivo], 403);
        }

        // Verificar que no haya ingresado ya a este examen (en cualquier ambiente)
        $ingresoPrevio = RegistroIngreso::where('id_estudiante', $estudiante->id_estudiante)
            ->whereHas('examenAmbiente', function ($query) use ($idExamen) {
                $query->where('id_examen', $idExamen);
            })->first();

        if ($ingresoPrevio) {
            $hora = Carbon::parse($ingresoPrevio->fecha_hora_ingreso)->format('H:i');
            return response()->json(['error' => "Este estudiante ya registró su ingreso a este examen a las {$hora}"], 409);
        }

        // Si pasa todas las validaciones, devolver los datos para confirmación visual
        return response()->json([
            'id_estudiante' => $estudiante->id_estudiante,
            'nombres' => $estudiante->nombres,
            'apellidos' => $estudiante->apellidos,
            'foto_url' => $estudiante->foto_url
        ]);
    }

    // Método para ejecutar el guardado tras la confirmación visual
    public function store(Request $request)
    {
        $request->validate([
            'id_estudiante' => 'required|integer',
            'id_examen_ambiente' => 'required|integer'
        ]);

        // Re-validación de seguridad por si el frontend envía una petición doble
        $examenAmbiente = ExamenAmbiente::find($request->id_examen_ambiente);
        $ingresoPrevio = RegistroIngreso::where('id_estudiante', $request->id_estudiante)
            ->whereHas('examenAmbiente', function ($query) use ($examenAmbiente) {
                $query->where('id_examen', $examenAmbiente->id_examen);
            })->first();

        if ($ingresoPrevio) {
            return response()->json(['error' => 'Registro duplicado detectado.'], 409);
        }

        // Guardar el registro con la hora exacta del servidor
        RegistroIngreso::create([
            'id_estudiante' => $request->id_estudiante,
            'id_examen_ambiente' => $request->id_examen_ambiente,
            'id_usuario' => auth()->id(),
            'fecha_hora_ingreso' => now(),
        ]);

        return response()->json(['success' => true]);
    }
}