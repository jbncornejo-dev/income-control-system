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
        // 1. Recuperar exámenes vigentes (no cancelados/anulados, cuya hora de
        //    fin aún no pasó) con sus relaciones.
        $examenes = Examen::with(['asignatura', 'tipo', 'examenesAmbientes.ambiente'])
            ->where(function ($q) {
                $q->whereNull('estado')
                  ->orWhereNotIn('estado', ['cancelado', 'anulado']);
            })
            ->whereRaw(
                "(fecha || ' ' || hora_inicio)::timestamp + (duracion_minutos || ' minutes')::interval > NOW()"
            )
            ->get()
            ->map(function ($examen) {
                return [
                    'id_examen'   => $examen->id_examen,
                    'asignatura'  => $examen->asignatura->nombre_asignatura ?? 'Sin asignatura',
                    'tipo'        => $examen->tipo->nombre ?? null,
                    'fecha'       => $examen->fecha,
                    'hora_inicio' => $examen->hora_inicio,
                    'estado'      => $examen->estado_actual,
                    'ambientes'   => $examen->examenesAmbientes->map(function ($pivot) {
                        return [
                            'id_examen_ambiente' => $pivot->id_examen_ambiente,
                            'id_ambiente'        => $pivot->ambiente->id_ambiente ?? null,
                            'nombre_ambiente'    => $pivot->ambiente->nombre_ambiente ?? 'Desconocido',
                        ];
                    }),
                ];
            });

        return Inertia::render('Control/RegistroIngreso/Index', [
            'examenes' => $examenes,
        ]);
    }

    /**
     * Validar al estudiante antes de confirmar el ingreso.
     *
     * Acepta:
     *  - código universitario (9 dígitos)
     *  - documento de identidad
     *  - payload QR completo ("ident:v1:CODIGO" o "ident:v1:CODIGO-TIMESTAMP")
     */
    public function validarEstudiante(Request $request)
    {
        $request->validate([
            'id_examen'       => 'required|integer',
            'dato_estudiante' => 'required|string',
        ]);

        $dato    = trim($request->input('dato_estudiante'));
        $idExamen = $request->input('id_examen');

        // ── Normalizar el payload del QR ─────────────────────────────
        // El QR del estudiante codifica "ident:v1:CODIGO_UNIVERSITARIO"
        // (opcionalmente con "-TIMESTAMP" si fue regenerado).
        $datoBusqueda = $dato;
        if (str_starts_with($dato, Estudiante::QR_PREFIX)) {
            $sinPrefijo    = substr($dato, strlen(Estudiante::QR_PREFIX));
            // Quitar posible sufijo "-TIMESTAMP" de regeneraciones
            $datoBusqueda  = explode('-', $sinPrefijo, 2)[0];
        }

        // ── Buscar estudiante ────────────────────────────────────────
        $estudiante = Estudiante::where('codigo_universitario', $datoBusqueda)
            ->orWhere('documento_identidad', $datoBusqueda)
            ->orWhere('codigo_qr', $dato)       // match exacto del payload QR completo
            ->first();

        if (! $estudiante) {
            return response()->json([
                'error' => 'No se encontró ningún estudiante con ese dato.',
            ], 404);
        }

        // ── Verificar habilitación ───────────────────────────────────
        $habilitacion = Habilitacion::where('id_estudiante', $estudiante->id_estudiante)
            ->where('id_examen', $idExamen)
            ->first();

        if (! $habilitacion || ! $habilitacion->estado_habilitado) {
            $motivo = $habilitacion->motivo_inhabilitacion
                ?? 'El estudiante no está habilitado para rendir este examen.';

            return response()->json([
                'error'      => $motivo,
                'estudiante' => [
                    'id_estudiante'       => $estudiante->id_estudiante,
                    'nombres'             => $estudiante->nombres,
                    'apellidos'           => $estudiante->apellidos,
                    'codigo_universitario'=> $estudiante->codigo_universitario,
                    'foto_url'            => $estudiante->foto_url,
                    'inhabilitado'        => true,
                ],
            ], 403);
        }

        // ── Verificar ingreso previo ─────────────────────────────────
        $ingresoPrevio = RegistroIngreso::where('id_estudiante', $estudiante->id_estudiante)
            ->whereHas('examenAmbiente', function ($query) use ($idExamen) {
                $query->where('id_examen', $idExamen);
            })->first();

        if ($ingresoPrevio) {
            $hora = Carbon::parse($ingresoPrevio->fecha_hora_ingreso)->format('H:i');

            return response()->json([
                'error'      => "Este estudiante ya registró su ingreso a las {$hora}.",
                'estudiante' => [
                    'id_estudiante'       => $estudiante->id_estudiante,
                    'nombres'             => $estudiante->nombres,
                    'apellidos'           => $estudiante->apellidos,
                    'codigo_universitario'=> $estudiante->codigo_universitario,
                    'foto_url'            => $estudiante->foto_url,
                    'ya_ingreso'          => true,
                    'hora_ingreso'        => $hora,
                ],
            ], 409);
        }

        // ── Todo OK: devolver datos para confirmación visual ─────────
        return response()->json([
            'id_estudiante'        => $estudiante->id_estudiante,
            'nombres'              => $estudiante->nombres,
            'apellidos'            => $estudiante->apellidos,
            'codigo_universitario' => $estudiante->codigo_universitario,
            'foto_url'             => $estudiante->foto_url,
        ]);
    }

    /**
     * Ejecutar el guardado tras la confirmación visual.
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_estudiante'      => 'required|integer',
            'id_examen_ambiente' => 'required|integer',
        ]);

        // Re-validación de seguridad ante peticiones dobles
        $examenAmbiente = ExamenAmbiente::findOrFail($request->id_examen_ambiente);
        $ingresoPrevio  = RegistroIngreso::where('id_estudiante', $request->id_estudiante)
            ->whereHas('examenAmbiente', function ($query) use ($examenAmbiente) {
                $query->where('id_examen', $examenAmbiente->id_examen);
            })->first();

        if ($ingresoPrevio) {
            return response()->json(['error' => 'Registro duplicado detectado.'], 409);
        }

        RegistroIngreso::create([
            'id_estudiante'      => $request->id_estudiante,
            'id_examen_ambiente' => $request->id_examen_ambiente,
            'id_usuario'         => auth()->id(),
            'fecha_hora_ingreso' => now(),
        ]);

        return response()->json(['success' => true]);
    }
}