<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Examen;
use App\Models\Grupo;
use App\Models\Rol;
use App\Models\User;
use Carbon\Carbon;
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

    private function usuario(string $nombreRol): User
    {
        $rol = Rol::firstOrCreate(['nombre_rol' => $nombreRol]);

        return User::create([
            'id_rol' => $rol->id_rol,
            'name' => ucfirst($nombreRol),
            'username' => str($nombreRol)->slug('_'),
            'email' => str($nombreRol)->slug().'@example.com',
            'password' => Hash::make('pass'),
        ]);
    }

    public function test_docente_ve_las_habilitaciones_de_un_examen_de_asignatura_que_dicta(): void
    {
        $examen = $this->crearExamen();
        $docente = $this->usuario('docente');

        Grupo::create([
            'id_asignatura' => $examen->id_asignatura,
            'id_usuario' => $docente->id,
            'gestion' => '2026',
            'nombre_grupo' => 'B',
        ]);

        $respuesta = $this->actingAs($docente)
            ->get("/examenes/{$examen->id_examen}/habilitaciones");

        $respuesta->assertOk();
    }

    public function test_docente_no_ve_las_habilitaciones_de_un_examen_que_no_dicta(): void
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
}
