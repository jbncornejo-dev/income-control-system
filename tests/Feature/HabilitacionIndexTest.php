<?php

namespace Tests\Feature;

use App\Models\Ambiente;
use App\Models\Asignatura;
use App\Models\AuditoriaLog;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\ExamenAmbiente;
use App\Models\Grupo;
use App\Models\Habilitacion;
use App\Models\Inscripcion;
use App\Models\RegistroIngreso;
use App\Models\Rol;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HabilitacionIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.pages.paths' => [resource_path('js/Pages')]]);
        Carbon::setTestNow('2026-09-20 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function crearExamen(string $sufijo = ''): Examen
    {
        $asignatura = Asignatura::create(['nombre_asignatura' => 'Habilitaciones '.$sufijo.uniqid()]);

        return Examen::create([
            'id_asignatura' => $asignatura->id_asignatura,
            'id_periodo' => $this->crearPeriodo()->id_periodo,
            'fecha' => '2026-09-21',
            'hora_inicio' => '10:00',
            'duracion_minutos' => 90,
        ]);
    }

    private function usuario(string $nombreRol, string $sufijo = ''): User
    {
        $rol = Rol::firstOrCreate(['nombre_rol' => $nombreRol]);

        return User::create([
            'id_rol' => $rol->id_rol,
            'name' => ucfirst($nombreRol).$sufijo,
            'username' => str($nombreRol)->slug('_').$sufijo,
            'email' => str($nombreRol)->slug().$sufijo.'@example.com',
            'password' => Hash::make('pass'),
        ]);
    }

    private function estudiante(): Estudiante
    {
        $unico = uniqid();

        return Estudiante::create([
            'codigo_universitario' => 'CU_'.$unico,
            'documento_identidad' => 'DOC_'.$unico,
            'nombres' => 'Estudiante',
            'apellidos' => 'De Prueba',
            'codigo_qr' => 'QR_'.$unico,
        ]);
    }

    private function grupoDe(int $idAsignatura, int $idUsuario, string $nombreGrupo): Grupo
    {
        return Grupo::create([
            'id_asignatura' => $idAsignatura,
            'id_usuario' => $idUsuario,
            'gestion' => '2026',
            'nombre_grupo' => $nombreGrupo,
        ]);
    }

    private function inscribir(int $idEstudiante, int $idGrupo): Inscripcion
    {
        return Inscripcion::create([
            'id_estudiante' => $idEstudiante,
            'id_grupo' => $idGrupo,
        ]);
    }

    private function habilitar(int $idEstudiante, int $idExamen): Habilitacion
    {
        return Habilitacion::create([
            'id_estudiante' => $idEstudiante,
            'id_examen' => $idExamen,
            'estado_habilitado' => true,
        ]);
    }

    /**
     * Escenario base de examen compartido entre dos docentes: el grupo "A"
     * pertenece al docente y el grupo "C" a otro docente, con un estudiante
     * inscrito y habilitado en cada grupo.
     *
     * @return array{examen: Examen, docente: User, otroDocente: User, grupoPropio: Grupo, grupoAjeno: Grupo, estudiantePropio: Estudiante, estudianteAjeno: Estudiante, habilitacionPropia: Habilitacion, habilitacionAjena: Habilitacion}
     */
    private function crearEscenarioCompartido(): array
    {
        $examen = $this->crearExamen();
        $docente = $this->usuario('docente');
        $otroDocente = $this->usuario('docente', '_2');

        $grupoPropio = $this->grupoDe($examen->id_asignatura, $docente->id, 'A');
        $grupoAjeno = $this->grupoDe($examen->id_asignatura, $otroDocente->id, 'C');
        $examen->grupos()->attach([$grupoPropio->id_grupo, $grupoAjeno->id_grupo]);

        $estudiantePropio = $this->estudiante();
        $estudianteAjeno = $this->estudiante();
        $this->inscribir($estudiantePropio->id_estudiante, $grupoPropio->id_grupo);
        $this->inscribir($estudianteAjeno->id_estudiante, $grupoAjeno->id_grupo);

        $habilitacionPropia = $this->habilitar($estudiantePropio->id_estudiante, $examen->id_examen);
        $habilitacionAjena = $this->habilitar($estudianteAjeno->id_estudiante, $examen->id_examen);

        return compact(
            'examen',
            'docente',
            'otroDocente',
            'grupoPropio',
            'grupoAjeno',
            'estudiantePropio',
            'estudianteAjeno',
            'habilitacionPropia',
            'habilitacionAjena'
        );
    }

    public function test_docente_ve_las_habilitaciones_de_estudiantes_de_sus_grupos(): void
    {
        $examen = $this->crearExamen();
        $docente = $this->usuario('docente');

        $grupo = $this->grupoDe($examen->id_asignatura, $docente->id, 'B');
        $examen->grupos()->attach($grupo->id_grupo);

        $estudiante = $this->estudiante();
        $this->inscribir($estudiante->id_estudiante, $grupo->id_grupo);
        $habilitacion = $this->habilitar($estudiante->id_estudiante, $examen->id_examen);

        $respuesta = $this->actingAs($docente)
            ->get("/examenes/{$examen->id_examen}/habilitaciones");

        $respuesta->assertOk();
        $this->assertSame(
            [$habilitacion->id_habilitacion],
            collect($respuesta->json('data'))->pluck('id_habilitacion')->all()
        );
    }

    public function test_docente_ve_una_lista_vacia_en_un_examen_sin_grupos_suyos(): void
    {
        $examen = $this->crearExamen();
        $otroDocente = $this->usuario('docente', '_2');

        $grupo = $this->grupoDe($examen->id_asignatura, $otroDocente->id, 'A');
        $examen->grupos()->attach($grupo->id_grupo);

        $estudiante = $this->estudiante();
        $this->inscribir($estudiante->id_estudiante, $grupo->id_grupo);
        $this->habilitar($estudiante->id_estudiante, $examen->id_examen);

        $respuesta = $this->actingAs($this->usuario('docente'))
            ->get("/examenes/{$examen->id_examen}/habilitaciones");

        $respuesta->assertOk();
        $this->assertEmpty($respuesta->json('data'));
    }

    public function test_administrador_accede_a_las_habilitaciones_de_cualquier_examen(): void
    {
        $examen = $this->crearExamen();

        $respuesta = $this->actingAs($this->usuario('administrador'))
            ->get("/examenes/{$examen->id_examen}/habilitaciones");

        $respuesta->assertOk();
    }

    public function test_docente_en_un_examen_compartido_ve_solo_habilitaciones_de_sus_estudiantes(): void
    {
        $escenario = $this->crearEscenarioCompartido();

        $respuesta = $this->actingAs($escenario['docente'])
            ->get("/examenes/{$escenario['examen']->id_examen}/habilitaciones");

        $respuesta->assertOk();
        $idsVistos = collect($respuesta->json('data'))->pluck('id_estudiante')->all();
        $this->assertContains($escenario['estudiantePropio']->id_estudiante, $idsVistos);
        $this->assertNotContains($escenario['estudianteAjeno']->id_estudiante, $idsVistos);
    }

    public function test_administrador_en_un_examen_compartido_ve_los_estudiantes_de_todos_los_docentes(): void
    {
        $escenario = $this->crearEscenarioCompartido();

        $respuesta = $this->actingAs($this->usuario('administrador'))
            ->get("/examenes/{$escenario['examen']->id_examen}/habilitaciones");

        $respuesta->assertOk();
        $idsVistos = collect($respuesta->json('data'))->pluck('id_estudiante')->all();
        $this->assertContains($escenario['estudiantePropio']->id_estudiante, $idsVistos);
        $this->assertContains($escenario['estudianteAjeno']->id_estudiante, $idsVistos);
    }

    public function test_docente_puede_actualizar_una_habilitacion_de_un_estudiante_de_sus_grupos_en_un_examen_compartido(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $escenario = $this->crearEscenarioCompartido();

        $respuesta = $this->actingAs($escenario['docente'])
            ->patch("/habilitaciones/{$escenario['habilitacionPropia']->id_habilitacion}", [
                'estado_habilitado' => false,
                'motivo_inhabilitacion' => 'No presentó el carnet',
            ]);

        $respuesta->assertFound();
        $this->assertDatabaseHas('habilitacion', [
            'id_habilitacion' => $escenario['habilitacionPropia']->id_habilitacion,
            'estado_habilitado' => false,
            'motivo_inhabilitacion' => 'No presentó el carnet',
        ]);
    }

    public function test_docente_no_actualiza_una_habilitacion_de_un_estudiante_de_otro_docente(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $escenario = $this->crearEscenarioCompartido();

        $respuesta = $this->actingAs($escenario['docente'])
            ->patch("/habilitaciones/{$escenario['habilitacionAjena']->id_habilitacion}", [
                'estado_habilitado' => false,
                'motivo_inhabilitacion' => 'No presentó el carnet',
            ]);

        $respuesta->assertForbidden();
        $this->assertDatabaseHas('habilitacion', [
            'id_habilitacion' => $escenario['habilitacionAjena']->id_habilitacion,
            'estado_habilitado' => true,
        ]);
    }

    public function test_docente_puede_agregar_estudiantes_de_sus_grupos_en_un_examen_compartido(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $escenario = $this->crearEscenarioCompartido();

        // Otro estudiante inscrito en el grupo del docente, aún no habilitado.
        $nuevo = $this->estudiante();
        $this->inscribir($nuevo->id_estudiante, $escenario['grupoPropio']->id_grupo);

        $respuesta = $this->actingAs($escenario['docente'])
            ->post("/examenes/{$escenario['examen']->id_examen}/habilitaciones", [
                'student_ids' => [$nuevo->id_estudiante],
            ]);

        $respuesta->assertFound();
        $this->assertDatabaseHas('habilitacion', [
            'id_estudiante' => $nuevo->id_estudiante,
            'id_examen' => $escenario['examen']->id_examen,
        ]);
    }

    public function test_docente_no_agrega_estudiantes_de_otro_docente(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $escenario = $this->crearEscenarioCompartido();

        $respuesta = $this->actingAs($escenario['docente'])
            ->post("/examenes/{$escenario['examen']->id_examen}/habilitaciones", [
                'student_ids' => [$escenario['estudianteAjeno']->id_estudiante],
            ]);

        $respuesta->assertForbidden();
        // La solicitud se rechazó antes de insertar nada nuevo.
        $this->assertSame(
            2,
            Habilitacion::where('id_examen', $escenario['examen']->id_examen)->count()
        );
    }

    public function test_filtro_y_cambio_masivo_incluyen_todas_las_paginas(): void
    {
        $examen = $this->crearExamen();
        $admin = $this->usuario('administrador');
        for ($i = 1; $i <= 18; $i++) {
            $estudiante = Estudiante::create([
                'codigo_universitario' => 'B'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'documento_identidad' => 'D'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'nombres' => 'Bloque',
                'apellidos' => 'Prueba',
            ]);
            Habilitacion::create(['id_examen' => $examen->id_examen, 'id_estudiante' => $estudiante->id_estudiante, 'estado_habilitado' => false, 'motivo_inhabilitacion' => 'Pendiente']);
        }

        $this->actingAs($admin)->getJson("/examenes/{$examen->id_examen}/habilitaciones?estado=inhabilitados&busqueda=Bloque")
            ->assertOk()->assertJsonPath('total', 18)->assertJsonCount(15, 'data');

        $this->actingAs($admin)->patch("/examenes/{$examen->id_examen}/habilitaciones", [
            'estado' => 'inhabilitados', 'busqueda' => 'Bloque', 'estado_habilitado' => true,
        ])->assertSessionHas('success');

        $this->assertSame(18, Habilitacion::where('id_examen', $examen->id_examen)->where('estado_habilitado', true)->count());
        $this->assertSame(18, AuditoriaLog::where('tabla_afectada', 'habilitacion')->count());
    }

    public function test_inhabilitacion_masiva_exige_motivo_y_docente_ajeno_no_puede_aplicarla(): void
    {
        $examen = $this->crearExamen();
        $url = "/examenes/{$examen->id_examen}/habilitaciones";
        $this->actingAs($this->usuario('administrador'))->patch($url, ['estado_habilitado' => false])
            ->assertSessionHasErrors('motivo_inhabilitacion');
        $this->actingAs($this->usuario('docente'))->patch($url, [
            'estado_habilitado' => false, 'motivo_inhabilitacion' => 'Falta requisito',
        ])->assertForbidden();
    }

    public function test_cambio_masivo_omite_estudiantes_que_ya_ingresaron(): void
    {
        $examen = $this->crearExamen();
        $admin = $this->usuario('administrador');
        $ambiente = Ambiente::create(['nombre_ambiente' => 'Aula '.uniqid(), 'capacidad' => 30]);
        $examenAmbiente = ExamenAmbiente::create(['id_examen' => $examen->id_examen, 'id_ambiente' => $ambiente->id_ambiente]);
        $estudiante = Estudiante::create(['codigo_universitario' => 'ING001', 'documento_identidad' => 'DOC001', 'nombres' => 'Ya', 'apellidos' => 'Ingresó']);
        $habilitacion = Habilitacion::create(['id_examen' => $examen->id_examen, 'id_estudiante' => $estudiante->id_estudiante]);
        RegistroIngreso::create(['id_estudiante' => $estudiante->id_estudiante, 'id_examen_ambiente' => $examenAmbiente->id_examen_ambiente, 'id_usuario' => $admin->id]);

        $this->actingAs($admin)->patch("/examenes/{$examen->id_examen}/habilitaciones", [
            'estado' => 'habilitados', 'estado_habilitado' => false, 'motivo_inhabilitacion' => 'Motivo común',
        ])->assertSessionHas('success', fn ($mensaje) => str_contains($mensaje, 'Omitidos por ingreso registrado: 1'));

        $this->assertTrue($habilitacion->fresh()->estado_habilitado);
        $this->actingAs($admin)->patch("/habilitaciones/{$habilitacion->id_habilitacion}", [
            'estado_habilitado' => false, 'motivo_inhabilitacion' => 'Motivo individual',
        ])->assertSessionHasErrors('estado_habilitado');
    }

    public function test_cambio_masivo_rechaza_filtro_todos_y_accion_incompatible(): void
    {
        $examen = $this->crearExamen();
        $url = "/examenes/{$examen->id_examen}/habilitaciones";
        $admin = $this->usuario('administrador');

        $this->actingAs($admin)->patch($url, ['estado' => 'todos', 'estado_habilitado' => true])
            ->assertSessionHasErrors('estado');
        $this->actingAs($admin)->patch($url, ['estado' => 'habilitados', 'estado_habilitado' => true])
            ->assertSessionHasErrors('estado');
    }
}
