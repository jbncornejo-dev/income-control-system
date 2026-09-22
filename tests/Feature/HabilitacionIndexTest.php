<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Grupo;
use App\Models\Habilitacion;
use App\Models\Inscripcion;
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
}
