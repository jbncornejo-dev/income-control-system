<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\ExamenAmbiente;
use App\Models\Habilitacion;
use App\Models\Periodo;
use App\Models\Rol;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HU17VentanasTiempoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config([
            'app.timezone' => 'America/La_Paz',
            'inertia.pages.paths' => [resource_path('js/Pages')],
            'inertia.testing.ensure_pages_exist' => false,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function crearEstudianteConUsuario(array $atributosEstudiante = []): array
    {
        $rol = Rol::firstOrCreate(['nombre_rol' => 'estudiante']);

        $codigo = $atributosEstudiante['codigo_universitario'] ?? ('2021'.random_int(10000, 99999));
        $estudiante = Estudiante::create(array_merge([
            'codigo_universitario' => $codigo,
            'documento_identidad'  => (string) random_int(1000000, 9999999),
            'nombres'              => 'Estudiante',
            'apellidos'            => 'Prueba',
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

    private function crearExamen(array $atributos = []): Examen
    {
        $asignatura = Asignatura::firstOrCreate(
            ['nombre_asignatura' => 'Sistemas Operativos'],
            ['codigo' => 'SIS214']
        );

        $periodo = Periodo::firstOrCreate(
            ['gestion' => 2026, 'tipo' => 'semestre', 'numero' => 2],
            ['fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-12-31']
        );

        return Examen::create(array_merge([
            'id_asignatura'    => $asignatura->id_asignatura,
            'id_periodo'       => $periodo->id_periodo,
            'fecha'            => '2026-06-15',
            'hora_inicio'      => '10:00:00',
            'duracion_minutos' => 90,
            'normas_generales' => 'Prohibido dispositivos electrónicos',
            'estado'           => null,
        ], $atributos));
    }

    private function habilitarEstudiante(Examen $examen, Estudiante $estudiante, ?User $personalControl = null): Habilitacion
    {
        return Habilitacion::create([
            'id_estudiante'        => $estudiante->id_estudiante,
            'id_examen'            => $examen->id_examen,
            'id_personal_control'  => $personalControl?->id,
            'estado_habilitado'    => true,
            'normas_particulares'  => null,
        ]);
    }

    private function crearAmbienteConExamen(Examen $examen): ExamenAmbiente
    {
        $ambiente = Ambiente::firstOrCreate(
            ['nombre_ambiente' => 'Laboratorio 1'],
            ['capacidad' => 40, 'ubicacion' => 'Bloque Central']
        );

        return ExamenAmbiente::create([
            'id_examen'   => $examen->id_examen,
            'id_ambiente' => $ambiente->id_ambiente,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. VENTANA QR ESTUDIANTE (24 Horas Previas al Inicio hasta Fin del Examen)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * CA-1: Si falta más de 24 horas para el examen, el estudiante no puede generar ni regenerar QR.
     */
    public function test_estudiante_no_puede_generar_o_regenerar_qr_con_mas_de_24_horas_de_anticipacion(): void
    {
        $examen = $this->crearExamen([
            'fecha' => '2026-06-15',
            'hora_inicio' => '10:00:00',
            'duracion_minutos' => 90,
        ]);

        [$estudiante, $usuario] = $this->crearEstudianteConUsuario(['puede_regenerar_qr' => true]);
        $this->habilitarEstudiante($examen, $estudiante);

        // Simulamos 24h y 1 minuto antes del inicio del examen
        Carbon::setTestNow(Carbon::parse('2026-06-14 09:59:00', 'America/La_Paz'));

        // El panel debe indicar que no está en ventana y no generar SVG
        $this->actingAs($usuario)
            ->get('/estudiante/panel')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Estudiantes/Panel', false)
                ->where('estudiante.en_ventana_qr', false)
                ->where('estudiante.qr_svg', null)
                ->has('estudiante.mensaje_ventana_qr')
            );

        // La petición directa al endpoint de regenerar debe ser rechazada con 403
        $this->actingAs($usuario)
            ->post('/estudiante/qr/regenerar')
            ->assertStatus(403);
    }

    /**
     * CA-1: Dentro de las 24 horas previas y hasta el fin del examen, con foto actualizada, sí puede regenerar.
     */
    public function test_estudiante_puede_regenerar_qr_dentro_de_la_ventana_de_24_horas_con_foto_actualizada(): void
    {
        $examen = $this->crearExamen([
            'fecha' => '2026-06-15',
            'hora_inicio' => '10:00:00',
            'duracion_minutos' => 90,
        ]);

        [$estudiante, $usuario] = $this->crearEstudianteConUsuario(['puede_regenerar_qr' => true]);
        $this->habilitarEstudiante($examen, $estudiante);

        // Simulamos 5 minutos dentro de la ventana de 24 horas (23h 55min antes del inicio)
        Carbon::setTestNow(Carbon::parse('2026-06-14 10:05:00', 'America/La_Paz'));

        // El panel debe indicar ventana activa y tener SVG
        $this->actingAs($usuario)
            ->get('/estudiante/panel')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Estudiantes/Panel', false)
                ->where('estudiante.en_ventana_qr', true)
                ->where('estudiante.qr_svg', fn ($svg) => filled($svg))
            );

        // Regeneración exitosa
        $codigoQrOriginal = $estudiante->codigo_qr;
        $response = $this->actingAs($usuario)->post('/estudiante/qr/regenerar');
        $response->assertRedirect('/estudiante/panel');

        $estudiante->refresh();
        $this->assertNotEquals($codigoQrOriginal, $estudiante->codigo_qr);
        // Regla HU-16: vuelve a bloquearse hasta nueva foto
        $this->assertFalse($estudiante->puede_regenerar_qr);
    }

    /**
     * Convivencia HU-16 + HU-17: Si está en ventana pero NO ha actualizado foto, se bloquea por HU-16.
     */
    public function test_estudiante_no_puede_regenerar_qr_dentro_de_las_24_horas_si_no_ha_actualizado_foto(): void
    {
        $examen = $this->crearExamen([
            'fecha' => '2026-06-15',
            'hora_inicio' => '10:00:00',
            'duracion_minutos' => 90,
        ]);

        [$estudiante, $usuario] = $this->crearEstudianteConUsuario(['puede_regenerar_qr' => false]);
        $this->habilitarEstudiante($examen, $estudiante);

        Carbon::setTestNow(Carbon::parse('2026-06-14 12:00:00', 'America/La_Paz'));

        $this->actingAs($usuario)
            ->post('/estudiante/qr/regenerar')
            ->assertStatus(403);
    }

    /**
     * CA-1: Si el examen ya finalizó, el estudiante no puede generar ni regenerar QR.
     */
    public function test_estudiante_no_puede_generar_o_regenerar_qr_una_vez_finalizado_el_examen(): void
    {
        $examen = $this->crearExamen([
            'fecha' => '2026-06-15',
            'hora_inicio' => '10:00:00',
            'duracion_minutos' => 90, // finaliza a las 11:30:00
        ]);

        [$estudiante, $usuario] = $this->crearEstudianteConUsuario(['puede_regenerar_qr' => true]);
        $this->habilitarEstudiante($examen, $estudiante);

        // Simulamos 1 minuto después de finalizado el examen
        Carbon::setTestNow(Carbon::parse('2026-06-15 11:31:00', 'America/La_Paz'));

        $this->actingAs($usuario)
            ->get('/estudiante/panel')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Estudiantes/Panel', false)
                ->where('estudiante.en_ventana_qr', false)
            );

        $this->actingAs($usuario)
            ->post('/estudiante/qr/regenerar')
            ->assertStatus(403);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. VENTANA DE REGISTRO DE INGRESOS (10 min antes de inicio hasta 10 min tras fin)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * CA-2: Control no puede validar ingreso antes de los 10 minutos previos al inicio.
     */
    public function test_control_no_puede_validar_ingreso_antes_de_los_10_minutos_previos_al_inicio(): void
    {
        $examen = $this->crearExamen([
            'fecha' => '2026-06-15',
            'hora_inicio' => '10:00:00',
            'duracion_minutos' => 90,
        ]);
        $agente = $this->crearPersonalControl();
        [$estudiante] = $this->crearEstudianteConUsuario();
        $this->habilitarEstudiante($examen, $estudiante, $agente);
        $examenAmbiente = $this->crearAmbienteConExamen($examen);

        // Simulamos 11 minutos antes del inicio (fuera de ventana de 10 min)
        Carbon::setTestNow(Carbon::parse('2026-06-15 09:49:00', 'America/La_Paz'));

        // Validar estudiante debe retornar 403
        $this->actingAs($agente)
            ->postJson('/registro-ingreso/validar', [
                'id_examen' => $examen->id_examen,
                'dato_estudiante' => $estudiante->codigo_universitario,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error', fn ($msg) => str_contains($msg, '10 minutos antes del inicio'));

        // Guardar directo debe retornar 403
        $this->actingAs($agente)
            ->postJson('/registro-ingreso', [
                'id_estudiante' => $estudiante->id_estudiante,
                'id_examen_ambiente' => $examenAmbiente->id_examen_ambiente,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error', fn ($msg) => str_contains($msg, '10 minutos antes del inicio'));
    }

    /**
     * CA-2: Control puede validar y registrar ingreso dentro de los límites exactos de la ventana.
     */
    public function test_control_puede_validar_y_confirmar_ingreso_en_los_limites_permitidos(): void
    {
        $examen = $this->crearExamen([
            'fecha' => '2026-06-15',
            'hora_inicio' => '10:00:00',
            'duracion_minutos' => 90, // Fin: 11:30:00, Fin ventana: 11:40:00
        ]);
        $agente = $this->crearPersonalControl();
        [$estudiante1] = $this->crearEstudianteConUsuario(['codigo_universitario' => '202100001']);
        [$estudiante2] = $this->crearEstudianteConUsuario(['codigo_universitario' => '202100002']);
        $this->habilitarEstudiante($examen, $estudiante1, $agente);
        $this->habilitarEstudiante($examen, $estudiante2, $agente);
        $examenAmbiente = $this->crearAmbienteConExamen($examen);

        // 1. Límite inferior exacto: 10 minutos antes del inicio (09:50:00)
        Carbon::setTestNow(Carbon::parse('2026-06-15 09:50:00', 'America/La_Paz'));

        $this->actingAs($agente)
            ->postJson('/registro-ingreso/validar', [
                'id_examen' => $examen->id_examen,
                'dato_estudiante' => $estudiante1->codigo_universitario,
            ])
            ->assertOk()
            ->assertJsonPath('id_estudiante', $estudiante1->id_estudiante);

        $this->actingAs($agente)
            ->postJson('/registro-ingreso', [
                'id_estudiante' => $estudiante1->id_estudiante,
                'id_examen_ambiente' => $examenAmbiente->id_examen_ambiente,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        // 2. Límite superior exacto: 10 minutos después del fin (11:40:00)
        Carbon::setTestNow(Carbon::parse('2026-06-15 11:40:00', 'America/La_Paz'));

        $this->actingAs($agente)
            ->postJson('/registro-ingreso/validar', [
                'id_examen' => $examen->id_examen,
                'dato_estudiante' => $estudiante2->codigo_universitario,
            ])
            ->assertOk()
            ->assertJsonPath('id_estudiante', $estudiante2->id_estudiante);

        $this->actingAs($agente)
            ->postJson('/registro-ingreso', [
                'id_estudiante' => $estudiante2->id_estudiante,
                'id_examen_ambiente' => $examenAmbiente->id_examen_ambiente,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    /**
     * CA-2: Control no puede validar ni confirmar después de 10 minutos de finalizado el examen.
     */
    public function test_control_no_puede_validar_ni_confirmar_despues_de_los_10_minutos_posteriores_al_fin(): void
    {
        $examen = $this->crearExamen([
            'fecha' => '2026-06-15',
            'hora_inicio' => '10:00:00',
            'duracion_minutos' => 90, // Fin: 11:30:00, límite: 11:40:00
        ]);
        $agente = $this->crearPersonalControl();
        [$estudiante] = $this->crearEstudianteConUsuario();
        $this->habilitarEstudiante($examen, $estudiante, $agente);
        $examenAmbiente = $this->crearAmbienteConExamen($examen);

        // Simulamos 11 minutos después del fin (11:41:00)
        Carbon::setTestNow(Carbon::parse('2026-06-15 11:41:00', 'America/La_Paz'));

        $this->actingAs($agente)
            ->postJson('/registro-ingreso/validar', [
                'id_examen' => $examen->id_examen,
                'dato_estudiante' => $estudiante->codigo_universitario,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error', fn ($msg) => str_contains($msg, '10 minutos después de la finalización'));

        $this->actingAs($agente)
            ->postJson('/registro-ingreso', [
                'id_estudiante' => $estudiante->id_estudiante,
                'id_examen_ambiente' => $examenAmbiente->id_examen_ambiente,
            ])
            ->assertStatus(403);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 3. VENTANA DE REGISTRO DE INCIDENCIAS (10 min antes de inicio a 10 min tras fin)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * CA-3: No se puede registrar incidencias fuera de la ventana permitida.
     */
    public function test_control_no_puede_registrar_incidencia_fuera_de_la_ventana_permitida(): void
    {
        $examen = $this->crearExamen([
            'fecha' => '2026-06-15',
            'hora_inicio' => '10:00:00',
            'duracion_minutos' => 90,
        ]);
        $agente = $this->crearPersonalControl();
        $examen->personalControl()->attach($agente->id);

        // 1. Antes de la ventana (15 min antes del inicio)
        Carbon::setTestNow(Carbon::parse('2026-06-15 09:45:00', 'America/La_Paz'));

        $this->actingAs($agente)
            ->postJson('/incidencias', [
                'id_examen' => $examen->id_examen,
                'tipo_incidencia' => 'Otro',
                'descripcion_motivo' => 'Incidencia antes de hora',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', fn ($msg) => str_contains($msg, 'solo está permitido entre 10 minutos antes del inicio'));

        // 2. Después de la ventana (15 min después del fin)
        Carbon::setTestNow(Carbon::parse('2026-06-15 11:45:00', 'America/La_Paz'));

        $this->actingAs($agente)
            ->postJson('/incidencias', [
                'id_examen' => $examen->id_examen,
                'tipo_incidencia' => 'Otro',
                'descripcion_motivo' => 'Incidencia tardía',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', fn ($msg) => str_contains($msg, 'solo está permitido entre 10 minutos antes del inicio'));
    }

    /**
     * CA-3: Se puede registrar incidencias dentro de la ventana de control.
     */
    public function test_control_puede_registrar_incidencia_dentro_de_la_ventana_permitida(): void
    {
        $examen = $this->crearExamen([
            'fecha' => '2026-06-15',
            'hora_inicio' => '10:00:00',
            'duracion_minutos' => 90,
        ]);
        $agente = $this->crearPersonalControl();
        $examen->personalControl()->attach($agente->id);

        // Simulamos 5 minutos antes del inicio (dentro de los 10 minutos previos)
        Carbon::setTestNow(Carbon::parse('2026-06-15 09:55:00', 'America/La_Paz'));

        $response = $this->actingAs($agente)->post('/incidencias', [
            'id_examen' => $examen->id_examen,
            'tipo_incidencia' => 'Problema de identificación',
            'descripcion_motivo' => 'Estudiante extravió su documento físico antes de ingresar.',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('incidencia', [
            'id_examen' => $examen->id_examen,
            'tipo_incidencia' => 'Problema de identificación',
        ]);
    }

    /**
     * Exámenes cancelados o anulados no habilitan ventana de operación.
     */
    public function test_examen_cancelado_o_anulado_no_habilita_ventanas(): void
    {
        $examen = $this->crearExamen([
            'fecha' => '2026-06-15',
            'hora_inicio' => '10:00:00',
            'duracion_minutos' => 90,
            'estado' => 'cancelado',
        ]);

        $this->assertFalse($examen->estaEnVentanaQr(Carbon::parse('2026-06-15 10:15:00', 'America/La_Paz')));
        $this->assertFalse($examen->estaEnVentanaOperacionControl(Carbon::parse('2026-06-15 10:15:00', 'America/La_Paz')));
    }
}

