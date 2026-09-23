<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexEstudianteRequest;
use App\Http\Requests\StoreEstudianteRequest;
use App\Http\Requests\UpdateEstudianteRequest;
use App\Models\Estudiante;
use App\Models\Rol;
use App\Models\User;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;

class StudentController extends Controller
{
    public function index(IndexEstudianteRequest $request)
    {
        $filtros = $request->validated();

        $students = Estudiante::query()
            ->when(isset($filtros['search']), function ($query) use ($filtros) {
                // Buscar literalmente el texto escrito, sin tratar los comodines de LIKE.
                $termino = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filtros['search']);

                $query->where(function ($q) use ($termino) {
                    $q->where('codigo_universitario', 'ilike', "%{$termino}%")
                        ->orWhere('documento_identidad', 'ilike', "%{$termino}%")
                        ->orWhere('nombres', 'ilike', "%{$termino}%")
                        ->orWhere('apellidos', 'ilike', "%{$termino}%")
                        ->orWhere('email', 'ilike', "%{$termino}%");
                });
            })
            ->orderBy('apellidos')
            ->paginate(15)
            ->withQueryString();

        // Mapea a los campos canónicos que usa la vista Vue (tabla y formularios).
        $mapped = $students->through(function ($s) {
            return [
                'id' => $s->id_estudiante,
                'codigo_universitario' => $s->codigo_universitario,
                'documento_identidad' => $s->documento_identidad,
                'nombres' => $s->nombres,
                'apellidos' => $s->apellidos,
                'codigo_qr' => $s->codigo_qr,
                'email' => $s->email,
            ];
        });

        return Inertia::render('Estudiantes/Index', [
            'estudiantes' => $mapped,
            'filtros' => [
                'search' => $filtros['search'] ?? null,
            ],
        ]);
    }

    public function store(StoreEstudianteRequest $request)
    {
        $datos = $request->validated();
        // El QR autogenerado codifica el código universitario.
        $datos['codigo_qr'] = Estudiante::qrPayload($datos['codigo_universitario']);

        try {
            DB::transaction(function () use ($datos) {
                $estudiante = Estudiante::create($datos);
                $this->crearCuentaEstudiante($estudiante, $datos['email'] ?? null);
            });
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

                return back()->withErrors(['codigo_universitario' => 'Registro duplicado.'])->withInput();
            }

            throw $e;
        }

        return redirect()->route('estudiantes.index')
            ->with('success', 'Estudiante registrado correctamente. Se creó su cuenta de acceso: la contraseña inicial es su documento de identidad y deberá cambiarla en el primer ingreso.');
    }

    public function update(UpdateEstudianteRequest $request, Estudiante $estudiante)
    {
        // codigo_universitario, documento_identidad y codigo_qr NO son editables;
        // solo se actualizan los campos definidos en el Form Request.
        $estudiante->update($request->safe()->only(['nombres', 'apellidos', 'email']));
        $this->sincronizarCuenta($estudiante);

        return back()->with('success', 'Estudiante actualizado correctamente.');
    }

    public function destroy(Estudiante $estudiante)
    {
        $mensaje = 'No se puede eliminar el estudiante porque tiene registros asociados en el sistema';

        if (
            $estudiante->habilitaciones()->exists()
            || $estudiante->registrosIngreso()->exists()
            || $estudiante->incidencias()->exists()
        ) {
            return back()->with('error', $mensaje);
        }

        try {
            DB::transaction(fn () => $estudiante->delete());
        } catch (QueryException $e) {
            // La clave foránea protege si se asocia un registro durante el borrado.
            if ($e->getCode() === '23503') {
                return back()->with('error', $mensaje);
            }

            throw $e;
        }

        return redirect()->route('estudiantes.index')
            ->with('success', 'Estudiante eliminado correctamente.');
    }

    /**
     * Renderiza el código QR del estudiante. Los estudiantes cargados antes de
     * la generación automática reciben su QR aquí (lazy backfill). Se usa SVG
     * porque no depende de la extensión GD del servidor.
     */
    public function qr(Estudiante $estudiante)
    {
        if ($estudiante->codigo_qr === null) {
            $estudiante->update(['codigo_qr' => Estudiante::qrPayload($estudiante->codigo_universitario)]);
        }

        $result = (new Builder(
            data: $estudiante->codigo_qr,
            size: 360,
            margin: 8,
            writer: new SvgWriter,
        ))->build();

        return response($result->getString(), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="qr-estudiante-'.$estudiante->id_estudiante.'.svg"',
            'Cache-Control' => 'private, no-store',
        ]);
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
        $existentes = $this->indicesExistentes();
        $vistos = [
            'codigo_universitario' => [],
            'documento_identidad' => [],
            'codigo_qr' => [],
            'email' => [],
        ];

        $creados = 0;
        $totalFilas = 0;
        $filaNumero = 0;
        $rechazados = [];
        $columnas = null;

        while (($fila = fgetcsv($handle, 0, ',')) !== false) {
            $filaNumero++;

            if ($this->esFilaVacia($fila)) {
                continue;
            }

            // La primera línea no vacía define las columnas; el resto son datos.
            if ($columnas === null) {
                $columnas = $this->mapearEncabezados($fila);

                if ($columnas === null) {
                    fclose($handle);

                    return response()->json([
                        'mensaje' => 'El encabezado del CSV no coincide con el formato esperado.',
                        'esperado' => [
                            'codigo_universitario,documento_identidad,nombres,apellidos',
                            '... + codigo_qr (opcional)',
                            '... + email (opcional)',
                            '... + codigo_qr,email (opcional)',
                        ],
                        'recibido' => $fila,
                    ], 422);
                }

                continue;
            }

            $totalFilas++;
            $datos = $this->parsearFila($fila, $columnas);
            $motivosRechazo = $this->motivosRechazo($datos, $existentes, $vistos);

            if ($motivosRechazo !== null) {
                $rechazados[] = [
                    'fila' => $filaNumero,
                    'datos' => $datos,
                    'motivos' => $motivosRechazo,
                ];

                continue;
            }

            if ($datos['codigo_qr'] === null) {
                $datos['codigo_qr'] = Estudiante::qrPayload($datos['codigo_universitario']);
            }

            try {
                DB::transaction(function () use ($datos) {
                    $estudiante = Estudiante::create($datos);
                    $this->crearCuentaEstudiante($estudiante, $datos['email']);
                });
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
                ? 'Importación completada. Estudiantes registrados correctamente. Cada uno tiene su cuenta de acceso con contraseña inicial (documento de identidad).'
                : 'Importación completada. No se registró ningún estudiante.',
            'total_filas' => $totalFilas,
            'exitosos' => $creados,
            'rechazados' => $rechazados,
        ]);
    }

    /**
     * Crea la cuenta de acceso del estudiante (rol estudiante). La contraseña
     * inicial es el documento de identidad y se obliga a cambiarla en el
     * primer ingreso, así no se depende de infraestructura de correo para
     * distribuir contraseñas generadas.
     */
    private function crearCuentaEstudiante(Estudiante $estudiante, ?string $email): User
    {
        $rolEstudiante = Rol::firstOrCreate(['nombre_rol' => 'estudiante']);

        $emailCuenta = $email;
        if ($emailCuenta !== null && User::query()->where('email', $emailCuenta)->exists()) {
            $emailCuenta = null;
        }

        return User::create([
            'id_rol' => $rolEstudiante->id_rol,
            'id_estudiante' => $estudiante->id_estudiante,
            'name' => trim($estudiante->nombres.' '.$estudiante->apellidos),
            'username' => $this->usernameUnico($estudiante->codigo_universitario),
            'email' => $emailCuenta,
            // Sin infraestructura de correo, la cuenta se considera verificada
            // desde el registro: /dashboard exige el middleware 'verified'.
            'email_verified_at' => now(),
            'password' => Hash::make($estudiante->documento_identidad),
            'debe_cambiar_password' => true,
        ]);
    }

    /**
     * Mantiene los datos de contacto de la cuenta en sincronía con el
     * registro del estudiante (nombres/apellidos y correo).
     */
    private function sincronizarCuenta(Estudiante $estudiante): void
    {
        $cuenta = $estudiante->user;

        if ($cuenta === null) {
            return;
        }

        $email = $estudiante->email;
        if ($email !== null && User::query()->where('email', $email)->where('id', '!=', $cuenta->id)->exists()) {
            $email = null;
        }

        $cuenta->update([
            'name' => trim($estudiante->nombres.' '.$estudiante->apellidos),
            'email' => $email,
        ]);
    }

    /**
     * Devuelve un username disponible partiendo del código universitario,
     * agregando un sufijo numérico si ya existe una cuenta con ese username.
     */
    private function usernameUnico(string $base): string
    {
        $username = $base;
        $sufijo = 1;

        while (User::query()->where('username', $username)->exists()) {
            $sufijoStr = (string) $sufijo++;
            $username = Str::limit($base, 49 - strlen($sufijoStr), '').$sufijoStr;
        }

        return $username;
    }

    /**
     * Interpreta la primera línea del CSV: cada columna debe coincidir con un
     * campo conocido y las 4 obligatorias deben estar presentes.
     *
     * @return array<string, int>|null Mapa campo => índice de columna, o null si es inválido.
     */
    private function mapearEncabezados(array $fila): ?array
    {
        $permisibles = [
            'codigo_universitario',
            'documento_identidad',
            'nombres',
            'apellidos',
            'codigo_qr',
            'email',
        ];

        $encabezados = array_map(fn ($campo) => $this->normalizar($campo), $fila);

        if (count(array_unique($encabezados)) !== count($encabezados)) {
            return null;
        }

        $columnas = [];
        foreach ($encabezados as $indice => $campo) {
            if (! in_array($campo, $permisibles, true)) {
                return null;
            }

            $columnas[$campo] = $indice;
        }

        foreach (['codigo_universitario', 'documento_identidad', 'nombres', 'apellidos'] as $obligatorio) {
            if (! array_key_exists($obligatorio, $columnas)) {
                return null;
            }
        }

        return $columnas;
    }

    /**
     * @param  array<string, int>  $columnas
     * @return array<string, mixed>
     */
    private function parsearFila(array $fila, array $columnas): array
    {
        $valor = function (string $campo) use ($fila, $columnas): string {
            $indice = $columnas[$campo] ?? null;

            return ($indice !== null && isset($fila[$indice]))
                ? $this->normalizar($fila[$indice])
                : '';
        };

        $email = $valor('email');

        return [
            'codigo_universitario' => $valor('codigo_universitario'),
            'documento_identidad' => $valor('documento_identidad'),
            'nombres' => $valor('nombres'),
            'apellidos' => $valor('apellidos'),
            'codigo_qr' => ! empty($valor('codigo_qr')) ? $valor('codigo_qr') : null,
            'email' => ! empty($email) ? strtolower($email) : null,
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

    /**
     * @return array<string, array<string, true>>
     */
    private function indicesExistentes(): array
    {
        $indices = [
            'codigo_universitario' => [],
            'documento_identidad' => [],
            'codigo_qr' => [],
            'email' => [],
        ];

        Estudiante::query()
            ->select(['codigo_universitario', 'documento_identidad', 'codigo_qr', 'email'])
            ->chunk(1000, function ($estudiantes) use (&$indices) {
                foreach ($estudiantes as $estudiante) {
                    $indices['codigo_universitario'][$estudiante->codigo_universitario] = true;
                    $indices['documento_identidad'][$estudiante->documento_identidad] = true;

                    if ($estudiante->codigo_qr !== null) {
                        $indices['codigo_qr'][$estudiante->codigo_qr] = true;
                    }

                    if ($estudiante->email !== null) {
                        $indices['email'][$estudiante->email] = true;
                    }
                }
            });

        return $indices;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, array<string, true>>  $existentes
     * @param  array<string, array<string, true>>  $vistos
     * @return array<int, string>|null
     */
    private function motivosRechazo(array $datos, array $existentes, array &$vistos): ?array
    {
        $motivos = [];

        if ($datos['codigo_universitario'] === '') {
            $motivos[] = 'El código universitario es obligatorio.';
        } elseif (mb_strlen($datos['codigo_universitario']) > 20) {
            $motivos[] = 'El código universitario no puede superar los 20 caracteres.';
        } elseif (! preg_match(Estudiante::REGEX_CODIGO_SIS, $datos['codigo_universitario'])) {
            $motivos[] = 'El código universitario debe tener 9 dígitos e iniciar con el año de ingreso.';
        } elseif (isset($existentes['codigo_universitario'][$datos['codigo_universitario']])) {
            $motivos[] = 'El código universitario ya está registrado.';
        } elseif (isset($vistos['codigo_universitario'][$datos['codigo_universitario']])) {
            $motivos[] = 'El código universitario está duplicado dentro del archivo.';
        }

        if ($datos['documento_identidad'] === '') {
            $motivos[] = 'El documento de identidad es obligatorio.';
        } elseif (mb_strlen($datos['documento_identidad']) > 20) {
            $motivos[] = 'El documento de identidad no puede superar los 20 caracteres.';
        } elseif (! preg_match(Estudiante::REGEX_DOCUMENTO_CI, $datos['documento_identidad'])) {
            $motivos[] = 'El documento de identidad debe tener entre 6 y 8 dígitos.';
        } elseif (isset($existentes['documento_identidad'][$datos['documento_identidad']])) {
            $motivos[] = 'El documento de identidad ya está registrado.';
        } elseif (isset($vistos['documento_identidad'][$datos['documento_identidad']])) {
            $motivos[] = 'El documento de identidad está duplicado dentro del archivo.';
        }

        if ($datos['nombres'] === '') {
            $motivos[] = 'Los nombres son obligatorios.';
        } elseif (mb_strlen($datos['nombres']) > 100) {
            $motivos[] = 'Los nombres no pueden superar los 100 caracteres.';
        } elseif (! preg_match(Estudiante::REGEX_NOMBRES, $datos['nombres'])) {
            $motivos[] = 'Los nombres solo pueden contener letras, espacios, apóstrofes y guiones.';
        }

        if ($datos['apellidos'] === '') {
            $motivos[] = 'Los apellidos son obligatorios.';
        } elseif (mb_strlen($datos['apellidos']) > 100) {
            $motivos[] = 'Los apellidos no pueden superar los 100 caracteres.';
        } elseif (! preg_match(Estudiante::REGEX_NOMBRES, $datos['apellidos'])) {
            $motivos[] = 'Los apellidos solo pueden contener letras, espacios, apóstrofes y guiones.';
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

        if ($datos['email'] !== null) {
            if (mb_strlen($datos['email']) > 255) {
                $motivos[] = 'El correo no puede superar los 255 caracteres.';
            } elseif (! preg_match(Estudiante::REGEX_EMAIL_UMSS, $datos['email'])) {
                $motivos[] = 'El correo debe tener un formato válido.';
            } elseif (isset($existentes['email'][$datos['email']])) {
                $motivos[] = 'El correo ya está registrado.';
            } elseif (isset($vistos['email'][$datos['email']])) {
                $motivos[] = 'El correo está duplicado dentro del archivo.';
            }
        }

        return $motivos === [] ? null : $motivos;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, array<string, true>>  $vistos
     */
    private function marcarVisto(array $datos, array &$vistos): void
    {
        $vistos['codigo_universitario'][$datos['codigo_universitario']] = true;
        $vistos['documento_identidad'][$datos['documento_identidad']] = true;

        if ($datos['codigo_qr'] !== null) {
            $vistos['codigo_qr'][$datos['codigo_qr']] = true;
        }

        if ($datos['email'] !== null) {
            $vistos['email'][$datos['email']] = true;
        }
    }

    private function mensajeDuplicado(string $mensaje): string
    {
        $errores = [
            'codigo_universitario' => 'El código universitario ya está registrado.',
            'documento_identidad' => 'El documento de identidad ya está registrado.',
            'codigo_qr' => 'El código QR ya está registrado.',
            'email' => 'El correo ya está registrado.',
        ];

        foreach ($errores as $columna => $texto) {
            if (str_contains($mensaje, $columna)) {
                return $texto;
            }
        }

        return 'El estudiante ya está registrado en la base de datos.';
    }
}
