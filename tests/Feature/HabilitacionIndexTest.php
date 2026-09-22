<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Estudiante;
use App\Models\Examen;
use App\Models\Grupo;
use App\Models\Habilitacion;
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

    public function test_docente_ve_las_habilitaciones_de_un_examen_cuyos_grupos_le_pertenecen(): void
    {
        $examen = $this->crearExamen();
        $docente = $this->usuario('docente');

        $grupo = Grupo::create([
            'id_asignatura' => $examen->id_asignatura,
            'id_usuario' => $docente->id,
            'gestion' => '2026',
            'nombre_grupo' => 'B',
        ]);
        $examen->grupos()->attach($grupo->id_grupo);

        $respuesta = $this->actingAs($docente)
            ->get("/examenes/{$examen->id_examen}/habilitaciones");

        $respuesta->assertOk();
    }

    public function test_docente_no_ve_las_habilitaciones_de_un_examen_que_no_cubre_ninguna_de_sus_grupos(): void
    {
        $examen = $this->crearExamen();

        $respuesta = $this->actingAs($this->usuario('docente'))
            ->get("/examenes/{$examen->id_examen}/habilitaciones");

        $respuesta->assertForbidden();
    }

    public function test_administrador_accede_a_las_habilitaciones_de_cualquier_examen(): void
    {
        $examen = $this->crearExamen();

        $respuesta = $this->actingAs($this->usuario('administrador'))
            ->get("/examenes/{$examen->id_examen}/habilitaciones");

        $respuesta->assertOk();
    }

    public function test_docente_no_ve_las_habilitaciones_de_un_examen_compartido_con_otro_docente(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $examen = $this->crearExamen();
        $docente = $this->usuario('docente');
        $otroDocente = $this->usuario('docente', '_2');

        $propio = Grupo::create([
            'id_asignatura' => $examen->id_asignatura,
            'id_usuario' => $docente->id,
            'gestion' => '2026',
            'nombre_grupo' => 'A',
        ]);
        $ajeno = Grupo::create([
            'id_asignatura' => $examen->id_asignatura,
            'id_usuario' => $otroDocente->id,
            'gestion' => '2026',
            'nombre_grupo' => 'C',
        ]);
        $examen->grupos()->attach([$propio->id_grupo, $ajeno->id_grupo]);

        // El examen cubre un grupo del docente, pero no le pertenece por
        // completo: lo gestiona el administrador, no el docente.
        $respuesta = $this->actingAs($docente)
            ->get("/examenes/{$examen->id_examen}/habilitaciones");

        $respuesta->assertForbidden();
    }

    public function test_docente_no_actualiza_una_habilitacion_de_un_examen_que_no_le_pertenece_por_completo(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $examen = $this->crearExamen();
        $otroDocente = $this->usuario('docente', '_2');

        $grupo = Grupo::create([
            'id_asignatura' => $examen->id_asignatura,
            'id_usuario' => $otroDocente->id,
            'gestion' => '2026',
            'nombre_grupo' => 'A',
        ]);
        $examen->grupos()->attach($grupo->id_grupo);

        $habilitacion = Habilitacion::create([
            'id_estudiante' => $this->estudiante()->id_estudiante,
            'id_examen' => $examen->id_examen,
            'estado_habilitado' => true,
        ]);

        $respuesta = $this->actingAs($this->usuario('docente'))
            ->patch("/habilitaciones/{$habilitacion->id_habilitacion}", [
                'estado_habilitado' => false,
                'motivo_inhabilitacion' => 'No presentó el carnet',
            ]);

        $respuesta->assertForbidden();
    }

    public function test_docente_no_agrega_estudiantes_a_un_examen_que_no_le_pertenece_por_completo(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $examen = $this->crearExamen();
        $otroDocente = $this->usuario('docente', '_2');

        $grupo = Grupo::create([
            'id_asignatura' => $examen->id_asignatura,
            'id_usuario' => $otroDocente->id,
            'gestion' => '2026',
            'nombre_grupo' => 'A',
        ]);
        $examen->grupos()->attach($grupo->id_grupo);

        $respuesta = $this->actingAs($this->usuario('docente'))
            ->post("/examenes/{$examen->id_examen}/habilitaciones", [
                'student_ids' => [$this->estudiante()->id_estudiante],
            ]);

        $respuesta->assertForbidden();
    }
}
