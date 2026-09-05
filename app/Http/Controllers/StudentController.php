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

    public function importar(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ], [
            'file.required' => 'Debe adjuntar un archivo CSV.',
            'file.mimes' => 'El archivo debe tener formato CSV.',
            'file.max' => 'El archivo excede el tamaño máximo permitido (10 MB).',
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $encabezadosEsperados = ['codigo_universitario', 'documento_identidad', 'nombres', 'apellidos', 'codigo_qr'];
        $existentes = $this->indicesExistentes();
        $vistos = [
            'codigo_universitario' => [],
            'documento_identidad' => [],
            'codigo_qr' => [],
        ];

        $creados = 0;
        $totalFilas = 0;
        $filaNumero = 0;
        $rechazados = [];
        $encabezadoProcesado = false;

        while (($fila = fgetcsv($handle, 0, ',')) !== false) {
            $filaNumero++;

            if ($this->esFilaVacia($fila)) {
                continue;
            }

            if (! $encabezadoProcesado) {
                $encabezados = array_map(fn ($campo) => $this->normalizar($campo), $fila);

                if ($encabezados !== $encabezadosEsperados) {
                    fclose($handle);

                    return response()->json([
                        'mensaje' => 'El encabezado del CSV no coincide con el formato esperado.',
                        'esperado' => $encabezadosEsperados,
                        'recibido' => $encabezados,
                    ], 422);
                }

                $encabezadoProcesado = true;

                continue;
            }

            $totalFilas++;
            $datos = $this->parsearFila($fila);
            $motivosRechazo = $this->motivosRechazo($datos, $existentes, $vistos);

            if ($motivosRechazo !== null) {
                $rechazados[] = [
                    'fila' => $filaNumero,
                    'datos' => $datos,
                    'motivos' => $motivosRechazo,
                ];

                continue;
            }

            try {
                Estudiante::create($datos);
                $creados++;
                $this->marcarVisto($datos, $vistos);
            } catch (QueryException $e) {
                $rechazados[] = [
                    'fila' => $filaNumero,
                    'datos' => $datos,
                    'motivos' => $e->getCode() === '23505'
                        ? [$this->mensajeDuplicado($e->getMessage())]
                        : ['Error inesperado al guardar el estudiante.'],
                ];
            }
        }

        fclose($handle);

        return response()->json([
            'mensaje' => $creados > 0
                ? 'Importación completada. Estudiantes registrados correctamente.'
                : 'Importación completada. No se registró ningún estudiante.',
            'total_filas' => $totalFilas,
            'exitosos' => $creados,
            'rechazados' => $rechazados,
        ]);
    }

    private function parsearFila(array $fila): array
    {
        return [
            'codigo_universitario' => $this->normalizar($fila[0] ?? ''),
            'documento_identidad' => $this->normalizar($fila[1] ?? ''),
            'nombres' => $this->normalizar($fila[2] ?? ''),
            'apellidos' => $this->normalizar($fila[3] ?? ''),
            'codigo_qr' => $this->normalizar($fila[4] ?? '') ?: null,
        ];
    }

    private function esFilaVacia(array $fila): bool
    {
        foreach ($fila as $campo) {
            if ($campo !== null && trim($campo) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizar(?string $valor): string
    {
        $valor = (string) $valor;

        if (! mb_check_encoding($valor, 'UTF-8')) {
            $valor = mb_convert_encoding($valor, 'UTF-8', 'ISO-8859-1');
        }

        return trim($valor);
    }

    private function indicesExistentes(): array
    {
        $indices = [
            'codigo_universitario' => [],
            'documento_identidad' => [],
            'codigo_qr' => [],
        ];

        Estudiante::query()
            ->select(['codigo_universitario', 'documento_identidad', 'codigo_qr'])
            ->chunk(1000, function ($estudiantes) use (&$indices) {
                foreach ($estudiantes as $estudiante) {
                    $indices['codigo_universitario'][$estudiante->codigo_universitario] = true;
                    $indices['documento_identidad'][$estudiante->documento_identidad] = true;

                    if ($estudiante->codigo_qr !== null) {
                        $indices['codigo_qr'][$estudiante->codigo_qr] = true;
                    }
                }
            });

        return $indices;
    }

    private function motivosRechazo(array $datos, array $existentes, array &$vistos): ?array
    {
        $motivos = [];

        if ($datos['codigo_universitario'] === '') {
            $motivos[] = 'El código universitario es obligatorio.';
        } elseif (mb_strlen($datos['codigo_universitario']) > 20) {
            $motivos[] = 'El código universitario no puede superar los 20 caracteres.';
        } elseif (isset($existentes['codigo_universitario'][$datos['codigo_universitario']])) {
            $motivos[] = 'El código universitario ya está registrado.';
        } elseif (isset($vistos['codigo_universitario'][$datos['codigo_universitario']])) {
            $motivos[] = 'El código universitario está duplicado dentro del archivo.';
        }

        if ($datos['documento_identidad'] === '') {
            $motivos[] = 'El documento de identidad es obligatorio.';
        } elseif (mb_strlen($datos['documento_identidad']) > 20) {
            $motivos[] = 'El documento de identidad no puede superar los 20 caracteres.';
        } elseif (isset($existentes['documento_identidad'][$datos['documento_identidad']])) {
            $motivos[] = 'El documento de identidad ya está registrado.';
        } elseif (isset($vistos['documento_identidad'][$datos['documento_identidad']])) {
            $motivos[] = 'El documento de identidad está duplicado dentro del archivo.';
        }

        if ($datos['nombres'] === '') {
            $motivos[] = 'Los nombres son obligatorios.';
        } elseif (mb_strlen($datos['nombres']) > 100) {
            $motivos[] = 'Los nombres no pueden superar los 100 caracteres.';
        }

        if ($datos['apellidos'] === '') {
            $motivos[] = 'Los apellidos son obligatorios.';
        } elseif (mb_strlen($datos['apellidos']) > 100) {
            $motivos[] = 'Los apellidos no pueden superar los 100 caracteres.';
        }

        if ($datos['codigo_qr'] !== null) {
            if (mb_strlen($datos['codigo_qr']) > 255) {
                $motivos[] = 'El código QR no puede superar los 255 caracteres.';
            } elseif (isset($existentes['codigo_qr'][$datos['codigo_qr']])) {
                $motivos[] = 'El código QR ya está registrado.';
            } elseif (isset($vistos['codigo_qr'][$datos['codigo_qr']])) {
                $motivos[] = 'El código QR está duplicado dentro del archivo.';
            }
        }

        return $motivos === [] ? null : $motivos;
    }

    private function marcarVisto(array $datos, array &$vistos): void
    {
        $vistos['codigo_universitario'][$datos['codigo_universitario']] = true;
        $vistos['documento_identidad'][$datos['documento_identidad']] = true;

        if ($datos['codigo_qr'] !== null) {
            $vistos['codigo_qr'][$datos['codigo_qr']] = true;
        }
    }

    private function mensajeDuplicado(string $mensaje): string
    {
        $errores = [
            'codigo_universitario' => 'El código universitario ya está registrado.',
            'documento_identidad' => 'El documento de identidad ya está registrado.',
            'codigo_qr' => 'El código QR ya está registrado.',
        ];

        foreach ($errores as $columna => $texto) {
            if (str_contains($mensaje, $columna)) {
                return $texto;
            }
        }

        return 'El estudiante ya está registrado en la base de datos.';
    }
}
