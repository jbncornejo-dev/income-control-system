<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\ExamenAmbiente;
use App\Models\Habilitacion;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HU16ActualizacionFotoQRTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Storage::fake('public');
    }

    private function crearEstudianteConUsuario(array $atributosEstudiante = []): array
    {
        $rol = Rol::firstOrCreate(['nombre_rol' => 'estudiante']);

        $codigo = $atributosEstudiante['codigo_universitario'] ?? '202100001';
        $estudiante = Estudiante::create(array_merge([
            'codigo_universitario' => $codigo,
            'documento_identidad'  => '8765432',
            'nombres'              => 'Juan Carlos',
            'apellidos'            => 'Perez Ramos',
            'email'                => $codigo . '@est.umss.edu',
            'codigo_qr'            => Estudiante::qrPayload($codigo),
            'puede_regenerar_qr'   => false,
        ], $atributosEstudiante));

        $usuario = User::create([
            'id_rol'                => $rol->id_rol,
            'id_estudiante'         => $estudiante->id_estudiante,
            'name'                  => $estudiante->nombres . ' ' . $estudiante->apellidos,
            'username'              => $estudiante->codigo_universitario,
            'email'                 => $estudiante->email,
            'password'              => Hash::make('secret123'),
            'email_verified_at'     => now(),
            'debe_cambiar_password' => false,
        ]);

        return [$estudiante, $usuario];
    }

    private function crearPersonalControl(): User
    {
        $rol = Rol::firstOrCreate(['nombre_rol' => 'personal de control de ingreso']);

        return User::create([
            'id_rol'                => $rol->id_rol,
            'name'                  => 'Agente Control',
            'username'              => 'control_' . uniqid(),
            'email'                 => 'control_' . uniqid() . '@umss.edu',
            'password'              => Hash::make('secret123'),
            'email_verified_at'     => now(),
            'debe_cambiar_password' => false,
        ]);
    }

    private function imagenFalsa(string $nombre = 'foto.png'): UploadedFile
    {
        // 1x1 PNG válido sin requerir la extensión GD
        $content = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($nombre, $content);
    }

    /**
     * CA-1: Al crear el estudiante (o subir su primera foto), se guarda una foto de referencia
     * permanente, separada de la foto de perfil actual que el estudiante sí puede actualizar.
     */
    public function test_ca1_creacion_con_foto_establece_foto_referencia_permanente(): void
    {
        [$estudiante, $usuario] = $this->crearEstudianteConUsuario([
            'foto_url' => '/storage/estudiantes/foto_inicial.jpg',
        ]);

        // Al crearse con foto, foto_referencia se inicializa automáticamente
        $this->assertEquals('/storage/estudiantes/foto_inicial.jpg', $estudiante->foto_referencia);
        $this->assertEquals('/storage/estudiantes/foto_inicial.jpg', $estudiante->foto_url);

        // Subir una foto nueva de perfil debe cambiar foto_url pero PRESERVAR foto_referencia
        $nuevaFoto = $this->imagenFalsa('nueva_foto_reciente.png');

        $this->actingAs($usuario)
            ->post('/estudiante/foto', ['foto' => $nuevaFoto])
            ->assertRedirect();

        $estudiante->refresh();

        $this->assertNotEquals('/storage/estudiantes/foto_inicial.jpg', $estudiante->foto_url);
        $this->assertEquals('/storage/estudiantes/foto_inicial.jpg', $estudiante->foto_referencia);
    }

    /**
     * CA-1: Al crear sin foto, la primera subida establece la foto de referencia permanente
     * y subidas posteriores no la alteran.
     */
    public function test_ca1_primera_subida_establece_referencia_y_subidas_posteriores_no_la_alteran(): void
    {
        [$estudiante, $usuario] = $this->crearEstudianteConUsuario([
            'foto_url'        => null,
            'foto_referencia' => null,
        ]);

        // Primera subida: establece foto actual y foto de referencia permanente
        $primeraFoto = $this->imagenFalsa('primera_foto.png');
        $this->actingAs($usuario)
            ->post('/estudiante/foto', ['foto' => $primeraFoto])
            ->assertRedirect();

        $estudiante->refresh();
        $this->assertNotNull($estudiante->foto_referencia);
        $this->assertEquals($estudiante->foto_url, $estudiante->foto_referencia);
        $fotoReferenciaOriginal = $estudiante->foto_referencia;

        // Segunda subida: actualiza foto_url pero conserva foto_referencia
        $segundaFoto = $this->imagenFalsa('segunda_foto.png');
        $this->actingAs($usuario)
            ->post('/estudiante/foto', ['foto' => $segundaFoto])
            ->assertRedirect();

        $estudiante->refresh();
        $this->assertNotEquals($fotoReferenciaOriginal, $estudiante->foto_url);
        $this->assertEquals($fotoReferenciaOriginal, $estudiante->foto_referencia);

        // Intento directo de mutar foto_referencia vía update no debe sobreescribir la referencia existente
        $estudiante->update(['foto_referencia' => '/storage/hack.jpg']);
        $estudiante->refresh();
        $this->assertEquals($fotoReferenciaOriginal, $estudiante->foto_referencia);
    }

    /**
     * CA-2: El botón "Regenerar código QR" queda deshabilitado (mostrando el mensaje exacto:
     * "Debes actualizar tu foto antes de generar un nuevo QR") si la foto actual no se actualizó
     * desde la última regeneración de QR.
     */
    public function test_ca2_intento_regenerar_qr_sin_actualizar_foto_es_bloqueado_en_endpoint(): void
    {
        [$estudiante, $usuario] = $this->crearEstudianteConUsuario([
            'puede_regenerar_qr' => false,
        ]);

        $qrOriginal = $estudiante->codigo_qr;

        // Intento por endpoint backend es rechazado con 403 y el mensaje exacto
        $response = $this->actingAs($usuario)
            ->postJson('/estudiante/qr/regenerar');

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Debes actualizar tu foto antes de generar un nuevo QR',
            'error'   => 'Debes actualizar tu foto antes de generar un nuevo QR',
        ]);

        $estudiante->refresh();
        $this->assertEquals($qrOriginal, $estudiante->codigo_qr);
    }

    /**
     * CA-2: En la respuesta del Panel de estudiante, se comparte puede_regenerar_qr = false
     * cuando no ha actualizado su foto.
     */
    public function test_ca2_panel_estudiante_prop_puede_regenerar_qr_es_false(): void
    {
        [$estudiante, $usuario] = $this->crearEstudianteConUsuario([
            'puede_regenerar_qr' => false,
        ]);

        $this->actingAs($usuario)
            ->get('/estudiante/panel')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Estudiantes/Panel')
                ->where('estudiante.puede_regenerar_qr', false)
            );
    }

    /**
     * CA-3: Al subir una foto nueva, se habilita nuevamente el botón de regenerar QR
     * (y una vez regenerado el QR, vuelve a bloquearse hasta una futura actualización de foto).
     */
    public function test_ca3_ciclo_completo_subir_foto_habilita_qr_y_regenerar_lo_consume(): void
    {
        [$estudiante, $usuario] = $this->crearEstudianteConUsuario([
            'puede_regenerar_qr' => false,
        ]);

        $qrInicial = $estudiante->codigo_qr;

        // 1. Antes de subir foto: bloqueado
        $this->actingAs($usuario)
            ->postJson('/estudiante/qr/regenerar')
            ->assertStatus(403);

        // 2. Subir nueva foto: habilita la regeneración
        $foto = $this->imagenFalsa('foto_reciente.png');
        $this->actingAs($usuario)
            ->post('/estudiante/foto', ['foto' => $foto])
            ->assertRedirect();

        $estudiante->refresh();
        $this->assertTrue($estudiante->puede_regenerar_qr);

        // En el panel, la prop refleja true
        $this->actingAs($usuario)
            ->get('/estudiante/panel')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Estudiantes/Panel')
                ->where('estudiante.puede_regenerar_qr', true)
            );

        // 3. Regenerar el QR tiene éxito y consume el permiso
        $this->actingAs($usuario)
            ->post('/estudiante/qr/regenerar')
            ->assertRedirect();

        $estudiante->refresh();
        $this->assertFalse($estudiante->puede_regenerar_qr);
        $this->assertNotEquals($qrInicial, $estudiante->codigo_qr);

        // 4. Intentar regenerar de nuevo sin otra foto vuelve a quedar bloqueado
        $this->actingAs($usuario)
            ->postJson('/estudiante/qr/regenerar')
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Debes actualizar tu foto antes de generar un nuevo QR',
            ]);
    }

    /**
     * CA-4: En la vista de Registro de Ingreso, el personal de control visualiza lado a lado
     * la foto de referencia y la foto actual del estudiante.
     */
    public function test_ca4_endpoint_validar_estudiante_retorna_foto_referencia_y_foto_actual(): void
    {
        $agente = $this->crearPersonalControl();

        $asignatura = Asignatura::create(['nombre_asignatura' => 'Física General']);
        $periodo = $this->crearPeriodo();
        $examen = Examen::create([
            'id_asignatura'    => $asignatura->id_asignatura,
            'id_periodo'       => $periodo->id_periodo,
            'fecha'            => now()->toDateString(),
            'hora_inicio'      => '08:00:00',
            'duracion_minutos' => 90,
        ]);

        $ambiente = Ambiente::create([
            'nombre_ambiente' => 'Aula 691',
            'capacidad'       => 50,
        ]);

        $examenAmbiente = ExamenAmbiente::create([
            'id_examen'   => $examen->id_examen,
            'id_ambiente' => $ambiente->id_ambiente,
        ]);

        [$estudiante, $usuarioEstudiante] = $this->crearEstudianteConUsuario([
            'foto_url'        => '/storage/estudiantes/foto_actual_2026.jpg',
            'foto_referencia' => '/storage/estudiantes/foto_referencia_2022.jpg',
        ]);

        Habilitacion::create([
            'id_examen'            => $examen->id_examen,
            'id_estudiante'        => $estudiante->id_estudiante,
            'id_personal_control'  => $agente->id,
            'estado_habilitado'    => true,
            'normas_particulares'  => 'Sin calculadora',
        ]);

        $response = $this->actingAs($agente)->postJson('/registro-ingreso/validar', [
            'id_examen'       => $examen->id_examen,
            'dato_estudiante' => $estudiante->codigo_universitario,
        ]);

        $response->assertOk();
        $response->assertJson([
            'id_estudiante'   => $estudiante->id_estudiante,
            'foto_url'        => '/storage/estudiantes/foto_actual_2026.jpg',
            'foto_actual'     => '/storage/estudiantes/foto_actual_2026.jpg',
            'foto_referencia' => '/storage/estudiantes/foto_referencia_2022.jpg',
        ]);
    }

    /**
     * CA-4: En el listado asignado del Registro de Ingreso se entregan foto_referencia y foto_actual.
     */
    public function test_ca4_registro_ingreso_index_entrega_ambas_fotos_en_mis_asignados(): void
    {
        $agente = $this->crearPersonalControl();

        $asignatura = Asignatura::create(['nombre_asignatura' => 'Química General']);
        $periodo = $this->crearPeriodo();
        $examen = Examen::create([
            'id_asignatura'    => $asignatura->id_asignatura,
            'id_periodo'       => $periodo->id_periodo,
            'fecha'            => now()->toDateString(),
            'hora_inicio'      => now()->format('H:i:s'),
            'duracion_minutos' => 120,
        ]);

        [$estudiante, $usuarioEstudiante] = $this->crearEstudianteConUsuario([
            'foto_url'        => '/storage/estudiantes/actual.jpg',
            'foto_referencia' => '/storage/estudiantes/referencia.jpg',
        ]);

        Habilitacion::create([
            'id_examen'           => $examen->id_examen,
            'id_estudiante'       => $estudiante->id_estudiante,
            'id_personal_control' => $agente->id,
            'estado_habilitado'   => true,
        ]);

        $this->actingAs($agente)
            ->get('/registro-ingreso')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Control/RegistroIngreso/Index')
                ->has('examenes.0.mis_asignados.0', fn (Assert $asignado) => $asignado
                    ->where('id_estudiante', $estudiante->id_estudiante)
                    ->where('foto_actual', '/storage/estudiantes/actual.jpg')
                    ->where('foto_referencia', '/storage/estudiantes/referencia.jpg')
                    ->etc()
                )
            );
    }

    /**
     * CA-2, CA-4 y CA-5: Inspección de templates Vue para validar consistencia de UI,
     * mensaje exacto y estructura responsive de dos columnas.
     */
    public function test_ca2_ca4_ca5_consistencia_visual_y_responsive_en_vistas_vue(): void
    {
        $panelVue = file_get_contents(resource_path('js/Pages/Estudiantes/Panel.vue'));
        $registroVue = file_get_contents(resource_path('js/Pages/Control/RegistroIngreso/Index.vue'));

        // CA-2: Mensaje exacto requerido y botón deshabilitado condicional
        $this->assertStringContainsString('Debes actualizar tu foto antes de generar un nuevo QR', $panelVue);
        $this->assertStringContainsString('!puedeRegenerarQr', $panelVue);

        // CA-4: Etiquetas claras para fotos lado a lado
        $this->assertStringContainsString('Foto de Referencia', $registroVue);
        $this->assertStringContainsString('Foto Actual', $registroVue);

        // CA-5: Responsive grid de 2 columnas y aspect ratio/object cover
        $this->assertStringContainsString('photo-comparison-grid', $registroVue);
        $this->assertStringContainsString('grid-template-columns: repeat(2, minmax(0, 1fr))', $registroVue);
        $this->assertStringContainsString('aspect-ratio: 1 / 1', $registroVue);
        $this->assertStringContainsString('object-fit: cover', $registroVue);

        // Fallbacks visuales para fotos faltantes
        $this->assertStringContainsString('Sin foto de referencia', $registroVue);
        $this->assertStringContainsString('Sin foto actual', $registroVue);
    }
}
