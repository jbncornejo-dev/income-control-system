<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\ExamenAmbiente;
use App\Models\Habilitacion;
use App\Models\Incidencia;
use App\Models\Periodo;
use App\Models\RegistroIngreso;
use App\Models\Rol;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use ZipArchive;

class HU26ReportesExportablesTest extends TestCase
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

    private function usuarioConRol(string $nombreRol): User
    {
        $rol = Rol::firstOrCreate(['nombre_rol' => $nombreRol]);

        return User::create([
            'id_rol'                => $rol->id_rol,
            'name'                  => 'Usuario ' . $nombreRol,
            'username'              => 'user_' . uniqid(),
            'email'                 => 'user_' . uniqid() . '@umss.edu',
            'password'              => Hash::make('secret123'),
            'email_verified_at'     => now(),
            'debe_cambiar_password' => false,
        ]);
    }

    private function crearExamen(array $atributos = []): Examen
    {
        $asignatura = Asignatura::firstOrCreate(
            ['nombre_asignatura' => 'Ingeniería de Software II'],
            ['codigo' => 'ISW202']
        );

        $periodo = Periodo::firstOrCreate(
            ['gestion' => 2026, 'tipo' => 'semestre', 'numero' => 2],
            ['fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-12-31']
        );

        return Examen::create(array_merge([
            'id_asignatura'    => $asignatura->id_asignatura,
            'id_periodo'       => $periodo->id_periodo,
            'fecha'            => '2026-06-20',
            'hora_inicio'      => '08:30:00',
            'duracion_minutos' => 90,
            'normas_generales' => 'Se prohíben dispositivos móviles.',
            'estado'           => null,
        ], $atributos));
    }

    private function crearAmbiente(Examen $examen, string $nombre, int $capacidad = 35): ExamenAmbiente
    {
        $ambiente = Ambiente::create([
            'nombre_ambiente' => $nombre,
            'capacidad'       => $capacidad,
            'ubicacion'       => 'Edificio Nuevo',
        ]);

        return ExamenAmbiente::create([
            'id_examen'   => $examen->id_examen,
            'id_ambiente' => $ambiente->id_ambiente,
        ]);
    }

    private function crearEstudiante(string $codigo, string $nombres, string $apellidos): Estudiante
    {
        return Estudiante::create([
            'codigo_universitario' => $codigo,
            'documento_identidad'  => (string) random_int(1000000, 9999999),
            'nombres'              => $nombres,
            'apellidos'            => $apellidos,
            'email'                => "{$codigo}@est.umss.edu",
            'codigo_qr'            => Estudiante::qrPayload($codigo),
            'puede_regenerar_qr'   => false,
        ]);
    }

    private function habilitarEstudiante(Examen $examen, Estudiante $estudiante): Habilitacion
    {
        return Habilitacion::create([
            'id_estudiante'       => $estudiante->id_estudiante,
            'id_examen'           => $examen->id_examen,
            'estado_habilitado'   => true,
            'normas_particulares' => null,
        ]);
    }

    private function registrarIngreso(ExamenAmbiente $examenAmbiente, Estudiante $estudiante, User $usuario, Carbon $hora): RegistroIngreso
    {
        return RegistroIngreso::create([
            'id_estudiante'      => $estudiante->id_estudiante,
            'id_examen_ambiente' => $examenAmbiente->id_examen_ambiente,
            'id_usuario'         => $usuario->id,
            'fecha_hora_ingreso' => $hora,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 1. CA-1: REPORTE DE ASISTENCIA (PDF Y EXCEL)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * CA-1: Exportación a PDF de asistencia con cabecera, totales por ambiente, ingresados (con hora) y ausentes.
     */
    public function test_asistencia_exportar_pdf_genera_documento_valido_con_estructura_completa(): void
    {
        $docente = $this->usuarioConRol('docente');
        $examen = $this->crearExamen();
        $amb1 = $this->crearAmbiente($examen, 'Aula 691B', 40);
        $amb2 = $this->crearAmbiente($examen, 'Aula 692C', 30);

        // Estudiantes
        $est1 = $this->crearEstudiante('202100101', 'Carlos', 'Gomez');
        $est2 = $this->crearEstudiante('202100102', 'Maria', 'Fernandez');
        $est3 = $this->crearEstudiante('202100103', 'Pedro', 'Alvarez'); // Queda ausente

        $this->habilitarEstudiante($examen, $est1);
        $this->habilitarEstudiante($examen, $est2);
        $this->habilitarEstudiante($examen, $est3);

        $this->registrarIngreso($amb1, $est1, $docente, Carbon::parse('2026-06-20 08:35:12', 'America/La_Paz'));
        $this->registrarIngreso($amb2, $est2, $docente, Carbon::parse('2026-06-20 08:40:45', 'America/La_Paz'));

        $response = $this->actingAs($docente)->get("/panel-asistencia/exportar/pdf?id_examen={$examen->id_examen}");

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.pdf', $response->headers->get('Content-Disposition'));

        // Verificar firma de archivo PDF
        $contenido = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $contenido);
        $this->assertGreaterThan(1000, strlen($contenido));
    }

    /**
     * CA-1: Exportación a Excel (.xlsx) de asistencia con totales por ambiente e ingresados/ausentes.
     */
    public function test_asistencia_exportar_excel_genera_archivo_xlsx_valido_y_estructurado(): void
    {
        $control = $this->usuarioConRol('personal de control de ingreso');
        $examen = $this->crearExamen();
        $amb1 = $this->crearAmbiente($examen, 'Laboratorio 1', 25);

        $est1 = $this->crearEstudiante('202100201', 'Lucia', 'Rojas');
        $est2 = $this->crearEstudiante('202100202', 'Jorge', 'Torrico');

        $this->habilitarEstudiante($examen, $est1);
        $this->habilitarEstudiante($examen, $est2);

        $this->registrarIngreso($amb1, $est1, $control, Carbon::parse('2026-06-20 08:32:00', 'America/La_Paz'));

        $response = $this->actingAs($control)->get("/panel-asistencia/exportar/excel?id_examen={$examen->id_examen}");

        $response->assertOk();
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));

        // Verificar que el contenido sea un archivo XLSX (ZIP OpenXML) válido
        $tempZip = tempnam(sys_get_temp_dir(), 'test_asist_');
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();
        file_put_contents($tempZip, $content);

        $zip = new ZipArchive();
        $openResult = $zip->open($tempZip);
        $this->assertTrue($openResult === true, 'El archivo descargado debe ser un archivo ZIP/XLSX válido');
        $this->assertNotFalse($zip->locateName('[Content_Types].xml'), 'Debe contener [Content_Types].xml propio de OpenXML');
        $this->assertNotFalse($zip->locateName('xl/workbook.xml'), 'Debe contener el libro workbook.xml de Excel');
        $zip->close();
        @unlink($tempZip);
    }

    /**
     * CA-1: La exportación sin especificar id_examen retorna 400 Bad Request.
     */
    public function test_asistencia_exportar_sin_id_examen_retorna_400(): void
    {
        $admin = $this->usuarioConRol('administrador');

        $this->actingAs($admin)->get('/panel-asistencia/exportar/pdf')->assertStatus(400);
        $this->actingAs($admin)->get('/panel-asistencia/exportar/excel')->assertStatus(400);
    }

    /**
     * CA-1: Estudiante no puede exportar asistencia (403 Forbidden).
     */
    public function test_asistencia_exportar_estudiante_recibe_403(): void
    {
        $estudiante = $this->usuarioConRol('estudiante');
        $examen = $this->crearExamen();

        $this->actingAs($estudiante)
            ->get("/panel-asistencia/exportar/pdf?id_examen={$examen->id_examen}")
            ->assertForbidden();

        $this->actingAs($estudiante)
            ->get("/panel-asistencia/exportar/excel?id_examen={$examen->id_examen}")
            ->assertForbidden();
    }

    /**
     * CA-1: Invitado sin sesión es redirigido al login.
     */
    public function test_asistencia_exportar_invitado_es_redirigido_al_login(): void
    {
        $examen = $this->crearExamen();

        $this->get("/panel-asistencia/exportar/pdf?id_examen={$examen->id_examen}")
            ->assertRedirect('/login');

        $this->get("/panel-asistencia/exportar/excel?id_examen={$examen->id_examen}")
            ->assertRedirect('/login');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 2. CA-2: REPORTE DE HISTÓRICO DE INCIDENCIAS (PDF Y EXCEL CON FILTROS)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * CA-2: Exportación a PDF de incidencias respetando filtros de examen y tipo.
     */
    public function test_incidencias_exportar_pdf_respeta_filtros_activos(): void
    {
        $admin = $this->usuarioConRol('administrador');
        $examen1 = $this->crearExamen(['fecha' => '2026-06-10']);
        $examen2 = $this->crearExamen(['fecha' => '2026-06-25']);

        $est1 = $this->crearEstudiante('202100301', 'Andres', 'Mendoza');
        $est2 = $this->crearEstudiante('202100302', 'Carla', 'Navarro');

        // Incidencia 1: Examen 1, Expulsión
        Incidencia::create([
            'id_examen'          => $examen1->id_examen,
            'id_estudiante'      => $est1->id_estudiante,
            'id_usuario'         => $admin->id,
            'tipo_incidencia'    => 'Expulsión',
            'descripcion_motivo' => 'Uso no autorizado de material de consulta',
            'fecha_hora'         => Carbon::parse('2026-06-10 09:15:00'),
        ]);

        // Incidencia 2: Examen 1, Intento indebido
        Incidencia::create([
            'id_examen'          => $examen1->id_examen,
            'id_estudiante'      => $est2->id_estudiante,
            'id_usuario'         => $admin->id,
            'tipo_incidencia'    => 'Intento indebido',
            'descripcion_motivo' => 'Intento de ingresar con código ajeno',
            'fecha_hora'         => Carbon::parse('2026-06-10 09:20:00'),
        ]);

        // Incidencia 3: Examen 2, Expulsión
        Incidencia::create([
            'id_examen'          => $examen2->id_examen,
            'id_estudiante'      => null,
            'id_usuario'         => $admin->id,
            'tipo_incidencia'    => 'Expulsión',
            'descripcion_motivo' => 'Incidencia en examen 2',
            'fecha_hora'         => Carbon::parse('2026-06-25 10:00:00'),
        ]);

        // Exportar filtrando SOLO Examen 1 y Tipo Expulsión
        $response = $this->actingAs($admin)->get("/incidencias/exportar/pdf?id_examen={$examen1->id_examen}&tipo_incidencia=Expulsi%C3%B3n");

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    /**
     * CA-2: Exportación a Excel de incidencias respetando filtros de fechas (desde / hasta).
     */
    public function test_incidencias_exportar_excel_respeta_filtros_de_rango_de_fechas(): void
    {
        $docente = $this->usuarioConRol('docente');
        $examen = $this->crearExamen();

        // Incidencia dentro del rango
        Incidencia::create([
            'id_examen'          => $examen->id_examen,
            'id_usuario'         => $docente->id,
            'tipo_incidencia'    => 'Cambio de ambiente',
            'descripcion_motivo' => 'Cambio de aula por falta de espacio',
            'fecha_hora'         => Carbon::parse('2026-05-15 10:00:00'),
        ]);

        // Incidencia fuera del rango
        Incidencia::create([
            'id_examen'          => $examen->id_examen,
            'id_usuario'         => $docente->id,
            'tipo_incidencia'    => 'Otro',
            'descripcion_motivo' => 'Incidencia en agosto fuera del rango',
            'fecha_hora'         => Carbon::parse('2026-08-20 10:00:00'),
        ]);

        $response = $this->actingAs($docente)->get('/incidencias/exportar/excel?desde=2026-05-01&hasta=2026-05-31');

        $response->assertOk();
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));

        // Validar formato XLSX
        $tempZip = tempnam(sys_get_temp_dir(), 'test_inc_');
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();
        file_put_contents($tempZip, $content);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($tempZip) === true);
        $this->assertNotFalse($zip->locateName('xl/workbook.xml'));
        $zip->close();
        @unlink($tempZip);
    }

    /**
     * CA-2: Estudiante es rechazado con 403 al intentar exportar incidencias.
     */
    public function test_incidencias_exportar_estudiante_es_rechazado(): void
    {
        $estudiante = $this->usuarioConRol('estudiante');

        $this->actingAs($estudiante)->get('/incidencias/exportar/pdf')->assertForbidden();
        $this->actingAs($estudiante)->get('/incidencias/exportar/excel')->assertForbidden();
    }

    /**
     * CA-2: Invitado no autenticado es redirigido al login.
     */
    public function test_incidencias_exportar_invitado_es_redirigido_al_login(): void
    {
        $this->get('/incidencias/exportar/pdf')->assertRedirect('/login');
        $this->get('/incidencias/exportar/excel')->assertRedirect('/login');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // 3. CA-3: CONSISTENCIA VISUAL Y BOTONES EN FRONTEND (VUE)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * CA-1 & CA-3: Verificar que el componente PanelAsistencia.vue tiene los botones y enlaces de exportación.
     */
    public function test_panel_asistencia_vue_contiene_botones_de_exportar(): void
    {
        $contenidoVue = file_get_contents(resource_path('js/Pages/Control/PanelAsistencia.vue'));

        $this->assertStringContainsString('<ReportExportLink', $contenidoVue);
        $this->assertStringContainsString('format="pdf"', $contenidoVue);
        $this->assertStringContainsString('format="excel"', $contenidoVue);
        $this->assertStringContainsString('/panel-asistencia/exportar/pdf', $contenidoVue);
        $this->assertStringContainsString('/panel-asistencia/exportar/excel', $contenidoVue);
    }

    /**
     * CA-2 & CA-3: Verificar que el componente Incidencias/Index.vue tiene los botones y binding de filtros.
     */
    public function test_incidencias_index_vue_contiene_botones_y_urls_de_exportacion_filtrada(): void
    {
        $contenidoVue = file_get_contents(resource_path('js/Pages/Incidencias/Index.vue'));

        $this->assertStringContainsString('<ReportExportLink', $contenidoVue);
        $this->assertStringContainsString('format="pdf"', $contenidoVue);
        $this->assertStringContainsString('format="excel"', $contenidoVue);
        $this->assertStringContainsString('/incidencias/exportar/pdf', $contenidoVue);
        $this->assertStringContainsString('/incidencias/exportar/excel', $contenidoVue);
        $this->assertStringContainsString('urlExportarPdf', $contenidoVue);
        $this->assertStringContainsString('urlExportarExcel', $contenidoVue);
    }
}
