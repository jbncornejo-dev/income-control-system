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
use App\Models\ConflictoIngreso;
use App\Models\Incidencia;
use Illuminate\Support\Facades\DB;

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
                $userId = auth()->id();

                $habilitacionesAsignadas = Habilitacion::with('estudiante')
                    ->where('id_examen', $examen->id_examen)
                    ->where('estado_habilitado', true)
                    ->where('id_personal_control', $userId)
                    ->get();

                $ingresadosIds = RegistroIngreso::whereHas('examenAmbiente', function ($q) use ($examen) {
                    $q->where('id_examen', $examen->id_examen);
                })->pluck('id_estudiante')->toArray();

                $misAsignados = $habilitacionesAsignadas->map(function ($hab) use ($ingresadosIds) {
                    return [
                        'id_habilitacion'       => $hab->id_habilitacion,
                        'id_estudiante'         => $hab->estudiante->id_estudiante,
                        'nombres'               => $hab->estudiante->nombres,
                        'apellidos'             => $hab->estudiante->apellidos,
                        'codigo_universitario'  => $hab->estudiante->codigo_universitario,
                        'documento_identidad'   => $hab->estudiante->documento_identidad,
                        'ya_ingreso'            => in_array($hab->id_estudiante, $ingresadosIds),
                    ];
                });

                return [
                    'id_examen'              => $examen->id_examen,
                    'asignatura'             => $examen->asignatura->nombre_asignatura ?? 'Sin asignatura',
                    'tipo'                   => $examen->tipo->nombre ?? null,
                    'fecha'                  => $examen->fecha,
                    'hora_inicio'            => $examen->hora_inicio,
                    'estado'                 => $examen->estado_actual,
                    'mis_pendientes_count'   => $misAsignados->where('ya_ingreso', false)->count(),
                    'mis_asignados'          => $misAsignados,
                    'ambientes'              => $examen->examenesAmbientes->map(function ($pivot) {
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
            Incidencia::create([
                'id_examen'          => $idExamen,
                'id_estudiante'      => null,
                'id_usuario'         => auth()->id(),
                'tipo_incidencia'    => 'Intento indebido',
                'descripcion_motivo' => 'Intento de ingreso rechazado: No se encontró ningún estudiante con el dato proporcionado.',
            ]);

            return response()->json([
                'error' => 'No se encontró ningún estudiante con ese dato.',
            ], 404);
        }

        // ── Verificar habilitación ───────────────────────────────────
        $habilitacion = Habilitacion::with('personalControl')
            ->where('id_estudiante', $estudiante->id_estudiante)
            ->where('id_examen', $idExamen)
            ->first();

        if (! $habilitacion || ! $habilitacion->estado_habilitado) {
            $motivo = $habilitacion->motivo_inhabilitacion
                ?? 'El estudiante no está habilitado para rendir este examen.';
            Incidencia::create([
                'id_examen'          => $idExamen,
                'id_estudiante'      => $estudiante->id_estudiante,
                'id_usuario'         => auth()->id(),
                'tipo_incidencia'    => 'Intento indebido',
                'descripcion_motivo' => 'Intento de ingreso rechazado: ' . $motivo,
            ]);

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
            
            $mensajeError = "Este estudiante ya registró su ingreso a las {$hora}.";
            
            Incidencia::create([
                'id_examen'          => $idExamen,
                'id_estudiante'      => $estudiante->id_estudiante,
                'id_usuario'         => auth()->id(),
                'tipo_incidencia'    => 'Intento indebido',
                'descripcion_motivo' => 'Intento de ingreso doble rechazado: ' . $mensajeError,
            ]);

            return response()->json([
                    'error'               => $mensajeError,              
                    'estudiante'          => [
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
        $esMiAsignado = $habilitacion->id_personal_control === auth()->id();
        $agenteAsignado = $habilitacion->personalControl?->name ?? 'Sin asignar';

        return response()->json([
            'id_estudiante'        => $estudiante->id_estudiante,
            'nombres'              => $estudiante->nombres,
            'apellidos'            => $estudiante->apellidos,
            'codigo_universitario' => $estudiante->codigo_universitario,
            'foto_url'             => $estudiante->foto_url,
            'es_mi_asignado'       => $esMiAsignado,
            'asignado_a'           => ! $esMiAsignado ? $agenteAsignado : null,
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

    /**
     * Sincronización en lote de registros offline con resolución de conflictos (HU-22).
     */
    public function sincronizarLote(Request $request)
    {
        $request->validate([
            'registros'                      => 'required|array',
            'registros.*.uuid_offline'       => 'required|string',
            'registros.*.id_estudiante'      => 'required|integer',
            'registros.*.id_examen_ambiente' => 'required|integer',
            'registros.*.fecha_hora_intento' => 'required|date',
        ]);

        $resultados = [
            'aceptados'  => [],
            'duplicados' => [],
            'rechazados' => [],
            'conflictos' => [],
        ];

        $userId = auth()->id();

        foreach ($request->input('registros') as $item) {
            $uuidOffline       = $item['uuid_offline'];
            $idEstudiante      = $item['id_estudiante'];
            $idExamenAmbiente  = $item['id_examen_ambiente'];
            $fechaHoraIntento  = Carbon::parse($item['fecha_hora_intento']);

            // 1. Idempotencia: Verificar si el UUID ya fue procesado antes
            $yaProcesado = RegistroIngreso::where('uuid_offline', $uuidOffline)->exists()
                || ConflictoIngreso::where('uuid_offline', $uuidOffline)->exists();

            if ($yaProcesado) {
                $resultados['duplicados'][] = [
                    'uuid_offline' => $uuidOffline,
                    'id_estudiante' => $idEstudiante,
                ];
                continue;
            }

            // Obtener ambiente y examen correspondiente
            $examenAmbiente = ExamenAmbiente::with('examen')->find($idExamenAmbiente);
            if (! $examenAmbiente || ! $examenAmbiente->examen) {
                $resultados['rechazados'][] = [
                    'uuid_offline' => $uuidOffline,
                    'id_estudiante' => $idEstudiante,
                    'motivo'       => 'Ambiente o examen no encontrado.',
                ];
                continue;
            }

            $idExamen = $examenAmbiente->id_examen;

            // 2. Validación de Habilitación
            $habilitacion = Habilitacion::where('id_estudiante', $idEstudiante)
                ->where('id_examen', $idExamen)
                ->first();

            if (! $habilitacion || ! $habilitacion->estado_habilitado) {
                $motivo = $habilitacion?->motivo_inhabilitacion ?? 'Estudiante inhabilitado para rendir este examen.';
                $resultados['rechazados'][] = [
                    'uuid_offline' => $uuidOffline,
                    'id_estudiante' => $idEstudiante,
                    'motivo'       => $motivo,
                ];
                continue;
            }

            // 3. Verificar si ya existe un ingreso para este estudiante en este examen
            $ingresoExistente = RegistroIngreso::where('id_estudiante', $idEstudiante)
                ->whereHas('examenAmbiente', fn ($q) => $q->where('id_examen', $idExamen))
                ->first();

            if ($ingresoExistente) {
                // Resolución de conflicto concurrente (Criterio 5): gana la hora más antigua
                $horaExistente = Carbon::parse($ingresoExistente->fecha_hora_ingreso);

                if ($fechaHoraIntento->lt($horaExistente)) {
                    // El nuevo intento offline es MÁS ANTIGUO: desplaza al existente
                    DB::transaction(function () use ($ingresoExistente, $uuidOffline, $idEstudiante, $idExamenAmbiente, $userId, $fechaHoraIntento, $horaExistente) {
                        // El existente pasa a ser conflicto
                        ConflictoIngreso::create([
                            'uuid_offline'       => $ingresoExistente->uuid_offline,
                            'id_estudiante'      => $ingresoExistente->id_estudiante,
                            'id_examen_ambiente' => $ingresoExistente->id_examen_ambiente,
                            'id_usuario'         => $ingresoExistente->id_usuario,
                            'fecha_hora_intento' => $horaExistente,
                            'motivo'             => 'Reemplazado por registro concurrente con hora más antigua.',
                        ]);

                        // Se actualiza el ingreso válido con el más antiguo
                        $ingresoExistente->update([
                            'id_examen_ambiente' => $idExamenAmbiente,
                            'id_usuario'         => $userId,
                            'fecha_hora_ingreso' => $fechaHoraIntento,
                            'uuid_offline'       => $uuidOffline,
                        ]);
                    });

                    $resultados['conflictos'][] = [
                        'uuid_offline' => $uuidOffline,
                        'id_estudiante' => $idEstudiante,
                        'detalle'      => 'Aceptado por ser más antiguo. Se movió el registro previo a conflictos.',
                    ];
                } else {
                    // El nuevo intento es POSTERIOR: se almacena directamente como conflicto
                    ConflictoIngreso::create([
                        'uuid_offline'       => $uuidOffline,
                        'id_estudiante'      => $idEstudiante,
                        'id_examen_ambiente' => $idExamenAmbiente,
                        'id_usuario'         => $userId,
                        'fecha_hora_intento' => $fechaHoraIntento,
                        'motivo'             => 'Registro concurrente duplicado (hora posterior al ya registrado).',
                    ]);

                    $resultados['conflictos'][] = [
                        'uuid_offline' => $uuidOffline,
                        'id_estudiante' => $idEstudiante,
                        'motivo'       => 'Estudiante ya ingresó previamente (conflicto guardado).',
                    ];
                }
                continue;
            }

            // 4. Inserción normal si no hay duplicados ni conflictos
            RegistroIngreso::create([
                'id_estudiante'      => $idEstudiante,
                'id_examen_ambiente' => $idExamenAmbiente,
                'id_usuario'         => $userId,
                'fecha_hora_ingreso' => $fechaHoraIntento,
                'uuid_offline'       => $uuidOffline,
            ]);

            $resultados['aceptados'][] = [
                'uuid_offline' => $uuidOffline,
                'id_estudiante' => $idEstudiante,
            ];
        }

        return response()->json($resultados);
    }
}