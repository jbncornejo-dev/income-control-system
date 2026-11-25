<?php

namespace Tests\Feature;

use App\Http\Controllers\ExamenController;
use App\Http\Controllers\HabilitacionController;
use App\Http\Controllers\StudentController;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\ExamenAmbiente;
use App\Models\Grupo;
use App\Models\Habilitacion;
use App\Models\Incidencia;
use App\Models\Inscripcion;
use App\Models\RegistroIngreso;
use App\Models\Rol;
use App\Models\User;
use App\Services\ReporteExportService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class BackendPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['inertia.testing.ensure_pages_exist' => false]);
        $this->withHeaders(['X-Inertia-Version' => (new HandleInertiaRequests)->version(Request::create('/'))]);
        Http::fake();
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create([
            'id_rol' => Rol::firstOrCreate(['nombre_rol' => $rol])->id_rol,
            'debe_cambiar_password' => false,
        ]);
    }

    private function estudiante(int $numero = 1): Estudiante
    {
        return Estudiante::create([
            'codigo_universitario' => sprintf('2020%05d', $numero),
            'documento_identidad' => (string) (1000000 + $numero),
            'nombres' => 'Ana', 'apellidos' => 'Pérez',
        ]);
    }

    private function examen(?Carbon $inicio = null): Examen
    {
        $inicio ??= now()->subMinutes(5);

        return Examen::create([
            'id_asignatura' => Asignatura::create(['nombre_asignatura' => 'Materia '.uniqid()])->id_asignatura,
            'id_periodo' => $this->crearPeriodo()->id_periodo,
            'fecha' => $inicio->toDateString(),
            'hora_inicio' => $inicio->format('H:i:s'),
            'duracion_minutos' => 120,
        ]);
    }

    public function test_student_exam_membership_uses_one_query_and_keeps_cancelled_exams_out(): void
    {
        $estudiante = $this->estudiante();
        $activo = $this->examen();
        $cancelado = $this->examen();
        $cancelado->update(['estado' => 'cancelado']);
        foreach ([$activo, $cancelado] as $examen) {
            Habilitacion::create(['id_estudiante' => $estudiante->id_estudiante, 'id_examen' => $examen->id_examen]);
        }

        DB::enableQueryLog();
        DB::flushQueryLog();
        $ids = $estudiante->examenesQuery()->pluck('id_examen')->all();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame([$activo->id_examen], $ids);
        $this->assertCount(1, $queries);
    }

    public function test_next_exam_and_qr_window_keep_ongoing_and_midnight_cases(): void
    {
        $estudiante = $this->estudiante();
        $ahora = Carbon::parse('2026-11-09 00:30:00', config('app.timezone'));
        $terminado = $this->examen($ahora->copy()->subHours(4));
        $vigente = $this->examen($ahora->copy()->subHour());
        $futuro = $this->examen($ahora->copy()->addHours(30));
        foreach ([$futuro, $terminado, $vigente] as $examen) {
            Habilitacion::create(['id_estudiante' => $estudiante->id_estudiante, 'id_examen' => $examen->id_examen]);
        }

        $this->assertSame($vigente->id_examen, $estudiante->proximoExamen($ahora)->id_examen);
        $this->assertTrue($estudiante->tieneExamenEnVentanaQr($ahora));
        $this->assertFalse($estudiante->tieneExamenEnVentanaQr($ahora->copy()->addHours(40)));
    }

    public function test_checkin_assignments_are_batched_and_entry_flags_are_per_exam(): void
    {
        $control = $this->usuario('personal de control de ingreso');
        $estudiante = $this->estudiante();
        $examenes = collect(range(1, 4))->map(fn () => $this->examen());
        foreach ($examenes as $examen) {
            Habilitacion::create([
                'id_estudiante' => $estudiante->id_estudiante,
                'id_examen' => $examen->id_examen,
                'id_personal_control' => $control->id,
            ]);
        }
        $ambiente = Ambiente::create(['nombre_ambiente' => 'Aula', 'capacidad' => 40]);
        $sala = ExamenAmbiente::create(['id_examen' => $examenes->first()->id_examen, 'id_ambiente' => $ambiente->id_ambiente]);
        RegistroIngreso::create([
            'id_estudiante' => $estudiante->id_estudiante,
            'id_examen_ambiente' => $sala->id_examen_ambiente,
            'id_usuario' => $control->id, 'fecha_hora_ingreso' => now(),
        ]);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->actingAs($control)->get('/registro-ingreso', ['X-Inertia' => 'true'])->assertOk();
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $datos = collect($response->json('props.examenes'))->keyBy('id_examen');
        $this->assertCount(4, $datos);
        foreach ($examenes as $index => $examen) {
            $this->assertSame($index === 0, $datos[$examen->id_examen]['mis_asignados'][0]['ya_ingreso']);
            $this->assertSame($index === 0 ? 0 : 1, $datos[$examen->id_examen]['mis_pendientes_count']);
        }
        $this->assertCount(1, $queries->filter(fn ($q) => str_starts_with($q['query'], 'select') && str_contains($q['query'], 'from "habilitacion"')));
    }

    public function test_partial_calendar_does_not_recalculate_stats_or_download_news(): void
    {
        $this->examen();
        foreach (['administrador' => 'Admin', 'docente' => 'Docente'] as $rol => $componente) {
            $usuario = $this->usuario($rol);
            DB::enableQueryLog();
            DB::flushQueryLog();
            $response = $this->actingAs($usuario)->get('/'.strtolower($componente).'/dashboard', [
                'X-Inertia' => 'true',
                'X-Inertia-Partial-Component' => $componente.'/Dashboard',
                'X-Inertia-Partial-Data' => 'examenesCalendario,calendarioData',
            ])->assertOk();
            $queries = collect(DB::getQueryLog());
            DB::disableQueryLog();

            $this->assertArrayHasKey('examenesCalendario', $response->json('props'));
            $this->assertArrayNotHasKey('stats', $response->json('props'));
            $this->assertFalse($queries->contains(fn ($q) => str_contains($q['query'], 'count(*)')));
        }
        Http::assertNothingSent();
    }

    public function test_attendance_polling_skips_catalog_and_exam_label_queries(): void
    {
        $control = $this->usuario('personal de control de ingreso');
        $examen = $this->examen();
        $this->app['env'] = 'local';
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $response = $this->actingAs($control)->get('/panel-asistencia?id_examen='.$examen->id_examen, [
                'X-Inertia' => 'true',
                'X-Inertia-Partial-Component' => 'Control/PanelAsistencia',
                'X-Inertia-Partial-Data' => 'ambientes,pendientes,resumen',
            ])->assertOk();
            $queries = collect(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            $this->app['env'] = 'testing';
        }
        $this->assertArrayNotHasKey('examenes', $response->json('props'));
        $this->assertSame(0, $response->json('props.resumen.habilitados'));
        $this->assertFalse($queries->contains(fn ($q) => str_contains($q['query'], 'from "asignatura"') || str_contains($q['query'], 'from "periodo"')));
    }

    public function test_partial_exam_search_skips_filter_catalog_queries(): void
    {
        $admin = $this->usuario('administrador');
        $this->examen();
        $this->app['env'] = 'local';
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $response = $this->actingAs($admin)->get('/examenes', [
                'X-Inertia' => 'true',
                'X-Inertia-Partial-Component' => 'Admin/Examenes/Index',
                'X-Inertia-Partial-Data' => 'examenes,filters,conteos',
            ])->assertOk();
            $queries = collect(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            $this->app['env'] = 'testing';
        }
        $this->assertArrayNotHasKey('periodos', $response->json('props'));
        $this->assertArrayNotHasKey('tipos', $response->json('props'));
        $this->assertFalse($queries->contains(fn ($q) => str_contains($q['query'], 'periodo_tipo_examen') || str_contains($q['query'], 'from "tipo_examen"')));
    }

    public function test_csv_existing_indexes_only_retain_matches_in_the_uploaded_file(): void
    {
        $rows = [];
        for ($i = 1; $i <= 1001; $i++) {
            $rows[] = ['codigo_universitario' => sprintf('2020%05d', $i), 'documento_identidad' => (string) (1000000 + $i), 'nombres' => 'Ana', 'apellidos' => 'Pérez'];
        }
        Estudiante::insert($rows);
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "codigo_universitario,documento_identidad,nombres,apellidos\n202000001,1000001,Ana,Pérez\n");
        rewind($handle);
        try {
            $method = new \ReflectionMethod(StudentController::class, 'indicesExistentes');
            $indices = $method->invoke(new StudentController, $handle);
            $this->assertCount(1, $indices['codigo_universitario']);
            $this->assertCount(1, $indices['documento_identidad']);
            $this->assertArrayHasKey('202000001', $indices['codigo_universitario']);
            $this->assertSame(0, ftell($handle));
        } finally {
            fclose($handle);
        }
    }

    public function test_csv_preserves_duplicate_messages_across_prefetch_batches(): void
    {
        $csv = "codigo_universitario,documento_identidad,nombres,apellidos\n202009999,1234567,Ana,Pérez\n";
        for ($i = 0; $i < 499; $i++) {
            $csv .= "invalido{$i},CI{$i},Ana,Pérez\n";
        }
        $csv .= "202009999,1234567,Ana,Pérez\n";
        $this->actingAs($this->usuario('administrador'))
            ->post('/estudiantes/importar', ['file' => UploadedFile::fake()->createWithContent('estudiantes.csv', $csv)])
            ->assertOk()
            ->assertJsonPath('total_filas', 501)
            ->assertJsonPath('exitosos', 1)
            ->assertJsonPath('rechazados.499.motivos.0', 'El código universitario está duplicado dentro del archivo.');
    }

    public function test_group_sync_inserts_in_sql_and_preserves_manual_disabling(): void
    {
        $docente = $this->usuario('docente');
        $examen = $this->examen();
        $grupo = Grupo::create(['id_asignatura' => $examen->id_asignatura, 'id_usuario' => $docente->id, 'nombre_grupo' => 'A', 'gestion' => 2026]);
        $examen->grupos()->attach($grupo);
        foreach ([1, 2] as $numero) {
            $estudiante = $this->estudiante($numero);
            Inscripcion::create(['id_grupo' => $grupo->id_grupo, 'id_estudiante' => $estudiante->id_estudiante]);
        }
        $inhabilitado = Habilitacion::create(['id_examen' => $examen->id_examen, 'id_estudiante' => Estudiante::first()->id_estudiante, 'estado_habilitado' => false, 'motivo_inhabilitacion' => 'Documento pendiente']);
        $method = new \ReflectionMethod(ExamenController::class, 'sincronizarHabilitacionesPorGrupos');
        DB::enableQueryLog();
        DB::flushQueryLog();
        $method->invoke(new ExamenController, $examen);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(1, $queries);
        $this->assertStringStartsWith('insert into "habilitacion"', $queries[0]['query']);
        $this->assertSame(2, $examen->habilitaciones()->count());
        $this->assertFalse($inhabilitado->fresh()->estado_habilitado);
        $this->assertSame('Documento pendiente', $inhabilitado->fresh()->motivo_inhabilitacion);
    }

    public function test_round_robin_keeps_its_position_across_batches(): void
    {
        $examen = $this->examen();
        $agentes = [$this->usuario('personal de control de ingreso')->id, $this->usuario('personal de control de ingreso')->id];
        $examen->personalControl()->attach($agentes);
        $estudiantes = [];
        for ($i = 1; $i <= 501; $i++) {
            $estudiantes[] = ['codigo_universitario' => sprintf('2020%05d', $i), 'documento_identidad' => (string) (1000000 + $i), 'nombres' => 'Ana', 'apellidos' => 'Pérez'];
        }
        Estudiante::insert($estudiantes);
        Habilitacion::insert(Estudiante::pluck('id_estudiante')->map(fn ($id) => ['id_examen' => $examen->id_examen, 'id_estudiante' => $id, 'estado_habilitado' => true])->all());

        $this->actingAs($this->usuario('administrador'))
            ->post('/examenes/'.$examen->id_examen.'/reparto-automatico')
            ->assertSessionHasNoErrors();

        $asignados = $examen->habilitaciones()->orderBy('id_habilitacion')->pluck('id_personal_control');
        $this->assertCount(501, $asignados);
        foreach ($asignados as $index => $id) {
            $this->assertSame($agentes[$index % 2], $id);
        }
    }

    public function test_available_controls_keep_their_conflict_of_interest_flag(): void
    {
        $admin = $this->usuario('administrador');
        $control = $this->usuario('personal de control de ingreso');
        $sinConflicto = $this->usuario('personal de control de ingreso');
        $estudiante = $this->estudiante();
        $control->update(['id_estudiante' => $estudiante->id_estudiante]);
        $examen = $this->examen();
        $grupo = Grupo::create(['id_asignatura' => $examen->id_asignatura, 'id_usuario' => $admin->id, 'nombre_grupo' => 'A', 'gestion' => 2026]);
        Inscripcion::create(['id_grupo' => $grupo->id_grupo, 'id_estudiante' => $estudiante->id_estudiante]);
        $request = Request::create('/examenes/'.$examen->id_examen.'/habilitaciones');
        $request->setUserResolver(fn () => $admin);
        $this->app['env'] = 'local';
        try {
            $response = (new HabilitacionController)->index($request, $examen);
            $props = (new \ReflectionProperty($response, 'props'))->getValue($response);
            $disponibles = $props['personalDisponible']()->keyBy('id');
        } finally {
            $this->app['env'] = 'testing';
        }
        $this->assertTrue($disponibles[$control->id]->conflicto_interes);
        $this->assertFalse($disponibles[$sinConflicto->id]->conflicto_interes);
    }

    public function test_excel_export_passes_a_filtered_lazy_collection_and_sql_count(): void
    {
        $admin = $this->usuario('administrador');
        $examen = $this->examen();
        foreach (['Expulsión', 'Cambio de ambiente'] as $tipo) {
            Incidencia::create(['id_examen' => $examen->id_examen, 'id_usuario' => $admin->id, 'tipo_incidencia' => $tipo, 'descripcion_motivo' => 'Detalle', 'fecha_hora' => now()]);
        }
        $archivo = tempnam(sys_get_temp_dir(), 'export-test-');
        $this->mock(ReporteExportService::class)->shouldReceive('exportarIncidenciasExcel')
            ->once()->withArgs(function ($filas, $filtros, $examenFiltro, $total) {
                $this->assertInstanceOf(LazyCollection::class, $filas);
                $this->assertSame(1, $total);
                $this->assertSame('Expulsión', $filtros['tipo_incidencia']);
                $this->assertNull($examenFiltro);
                $this->assertSame(['Expulsión'], $filas->pluck('tipo_incidencia')->all());

                return true;
            })->andReturn(new BinaryFileResponse($archivo));
        try {
            $this->actingAs($admin)->get('/incidencias/exportar/excel?tipo_incidencia=Expulsi%C3%B3n')->assertOk();
        } finally {
            unlink($archivo);
        }
    }

    public function test_legacy_excel_keeps_every_incident_when_timestamps_tie_across_batches(): void
    {
        $admin = $this->usuario('administrador');
        $examen = $this->examen();
        $hora = now()->format('Y-m-d H:i:s');
        $filas = [];
        for ($i = 1; $i <= 501; $i++) {
            $filas[] = ['id_examen' => $examen->id_examen, 'id_usuario' => $admin->id, 'tipo_incidencia' => 'Expulsión', 'descripcion_motivo' => 'Detalle '.$i, 'fecha_hora' => $hora];
        }
        Incidencia::insert($filas);
        $response = $this->actingAs($admin)->get('/incidencias/exportar?formato=excel')->assertOk();
        preg_match_all('/Detalle (\d+)/', $response->getContent(), $coincidencias);
        $this->assertCount(501, $coincidencias[1]);
        $this->assertCount(501, array_unique($coincidencias[1]));
    }
}
